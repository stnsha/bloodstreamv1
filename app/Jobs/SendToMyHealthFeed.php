<?php

namespace App\Jobs;

use App\Models\TestResult;
use App\Services\MyHealthFeedDeliveryService;
use App\Services\MyHealthFeedService;
use App\Services\PatientIdentityService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Sends a completed / released test result's patient profile to the MyHealth
 * lab feed, in place of the AI review (see SendToAIServer::MYHEALTH_FEED_FROM).
 * Payload and transport live in MyHealthFeedService. A successful POST marks
 * the result is_reviewed=1 so the unreviewed sweepers do not re-send it.
 *
 * With markReviewed = false (testing overlap, SendToAIServer::MYHEALTH_FEED_TEST_FROM
 * until MYHEALTH_FEED_FROM) the feed is a copy alongside the AI review: it is sent
 * even when the result is already reviewed and never changes is_reviewed, which
 * stays owned by the AI review.
 *
 * Every attempt is recorded in myhealth_feed_deliveries (PENDING, then SENT /
 * FAILED / SKIPPED, with the HTTP status and the payload sent), which the
 * myhealth:* scheduled commands use to retry and reconcile.
 */
class SendToMyHealthFeed implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;

    public $tries = 3;

    public $backoff = [30, 60, 120];

    public $uniqueFor = 3600;

    public $testResultId;

    public $markReviewed;

    public function __construct(int $testResultId, bool $markReviewed = true)
    {
        $this->testResultId = $testResultId;
        $this->markReviewed = $markReviewed;
        $this->onQueue('ai-reviews');
    }

    public function uniqueId(): string
    {
        return "send_to_myhealth_feed_{$this->testResultId}";
    }

    public function handle(
        MyHealthFeedService $feedService,
        PatientIdentityService $identityService,
        MyHealthFeedDeliveryService $deliveryService
    ): void {
        $startTime = microtime(true);

        $testResult = TestResult::with('patient')->find($this->testResultId);

        if (! $testResult) {
            Log::channel('myhealth-feed')->warning('SendToMyHealthFeed: test result not found, skipping', [
                'test_result_id' => $this->testResultId,
            ]);

            return;
        }

        if ($this->markReviewed && $testResult->is_reviewed) {
            Log::channel('myhealth-feed')->info('SendToMyHealthFeed: test result already reviewed, skipping', [
                'test_result_id' => $this->testResultId,
            ]);

            return;
        }

        Log::channel('myhealth-feed')->info('SendToMyHealthFeed: starting', [
            'test_result_id' => $this->testResultId,
            'lab_no' => $testResult->lab_no,
            'attempt' => $this->attempts(),
            'mark_reviewed' => $this->markReviewed,
        ]);

        $deliveryService->start($this->testResultId);

        // Fill a missing ref_id and replace a wrong patient name with the ODB
        // name before the profile is built; fail-open.
        $identityService->verifyForFeed($testResult);
        $testResult->refresh()->load('patient');

        try {
            $payload = $feedService->buildPayload($testResult);
        } catch (RuntimeException $e) {
            // Missing / unknown IC number: retrying cannot fix it.
            Log::channel('myhealth-feed')->error('SendToMyHealthFeed: cannot build feed', [
                'test_result_id' => $this->testResultId,
                'patient_id' => $testResult->patient_id,
                'error' => $e->getMessage(),
            ]);

            $deliveryService->skipped($this->testResultId);

            return;
        }

        try {
            $response = $feedService->send($payload['body']);
        } catch (Throwable $e) {
            // Connection failure or feed not configured: no HTTP status
            $deliveryService->failed($this->testResultId, null, $payload['body']);

            throw $e;
        }

        if (! $feedService->isAccepted($response)) {
            $deliveryService->failed($this->testResultId, $response->status(), $payload['body']);

            throw new RuntimeException(sprintf(
                'MyHealth feed rejected test result %d: HTTP %d %s',
                $this->testResultId,
                $response->status(),
                mb_substr($response->body(), 0, 1000)
            ));
        }

        $deliveryService->sent($this->testResultId, $response->status(), $payload['body']);

        if (! $this->markReviewed) {
            Log::channel('myhealth-feed')->info('SendToMyHealthFeed: completed (testing overlap copy, is_reviewed left to the AI review)', array_merge([
                'test_result_id' => $this->testResultId,
                'lab_no' => $testResult->lab_no,
                'http_status' => $response->status(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ], $payload['stats']));

            return;
        }

        try {
            DB::beginTransaction();

            TestResult::whereKey($this->testResultId)->update(['is_reviewed' => true]);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            Log::channel('myhealth-feed')->error('SendToMyHealthFeed: feed sent but failed to mark test result reviewed', [
                'test_result_id' => $this->testResultId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        Log::channel('myhealth-feed')->info('SendToMyHealthFeed: completed', array_merge([
            'test_result_id' => $this->testResultId,
            'lab_no' => $testResult->lab_no,
            'http_status' => $response->status(),
            'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
        ], $payload['stats']));
    }

    public function failed(Throwable $exception): void
    {
        Log::channel('myhealth-feed')->error('SendToMyHealthFeed: failed after all attempts', [
            'test_result_id' => $this->testResultId,
            'error' => $exception->getMessage(),
        ]);

        app(MyHealthFeedDeliveryService::class)->failedFinal($this->testResultId);
    }
}
