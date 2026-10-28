<?php

namespace App\Jobs;

use App\Models\TestResult;
use App\Services\MyHealthFeedService;
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
 */
class SendToMyHealthFeed implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;

    public $tries = 3;

    public $backoff = [30, 60, 120];

    public $uniqueFor = 3600;

    public $testResultId;

    public function __construct(int $testResultId)
    {
        $this->testResultId = $testResultId;
        $this->onQueue('ai-reviews');
    }

    public function uniqueId(): string
    {
        return "send_to_myhealth_feed_{$this->testResultId}";
    }

    public function handle(MyHealthFeedService $feedService): void
    {
        $startTime = microtime(true);

        $testResult = TestResult::with('patient')->find($this->testResultId);

        if (! $testResult) {
            Log::channel('myhealth-feed')->warning('SendToMyHealthFeed: test result not found, skipping', [
                'test_result_id' => $this->testResultId,
            ]);

            return;
        }

        if ($testResult->is_reviewed) {
            Log::channel('myhealth-feed')->info('SendToMyHealthFeed: test result already reviewed, skipping', [
                'test_result_id' => $this->testResultId,
            ]);

            return;
        }

        Log::channel('myhealth-feed')->info('SendToMyHealthFeed: starting', [
            'test_result_id' => $this->testResultId,
            'lab_no' => $testResult->lab_no,
            'attempt' => $this->attempts(),
        ]);

        try {
            $payload = $feedService->buildPayload($testResult);
        } catch (RuntimeException $e) {
            // Missing / unknown IC number: retrying cannot fix it.
            Log::channel('myhealth-feed')->error('SendToMyHealthFeed: cannot build feed', [
                'test_result_id' => $this->testResultId,
                'patient_id' => $testResult->patient_id,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $response = $feedService->send($payload['body']);

        if (! $feedService->isAccepted($response)) {
            throw new RuntimeException(sprintf(
                'MyHealth feed rejected test result %d: HTTP %d %s',
                $this->testResultId,
                $response->status(),
                mb_substr($response->body(), 0, 1000)
            ));
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
    }
}
