<?php

namespace App\Services;

use App\Jobs\SendToAIServer;
use App\Models\MyHealthFeedDelivery;
use App\Models\TestResult;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Handles the callback from Insyte (the MyHealth lab feed server) reporting how
 * it processed a test result we sent.
 *
 * http_status sent by Insyte, and what it does here:
 *  - 200, 201, 204, 409 -> completed:  delivery SENT; is_reviewed = 1 (live period only)
 *  - 202                -> processing: nothing changes, a final callback is expected
 *  - other 4xx          -> rejected:   delivery SKIPPED (the data must be fixed, a resend
 *                                      would fail the same way; not retried)
 *  - 5xx                -> failed:     delivery FAILED (myhealth:retry-failed-feeds resends)
 *
 * Before SendToAIServer::MYHEALTH_FEED_FROM (Insyte testing period) the AI
 * review owns is_reviewed, so a completed callback never changes it
 * (ignored = true); the delivery status is still recorded.
 */
class InsyteService
{
    const OUTCOME_COMPLETED = 'completed';

    const OUTCOME_PROCESSING = 'processing';

    const OUTCOME_REJECTED = 'rejected';

    const OUTCOME_FAILED = 'failed';

    /**
     * @return array{test_result_id: int, http_status: int, outcome: string, delivery_status: string|null, is_reviewed: bool, ignored: bool}
     *
     * @throws ModelNotFoundException when the test result does not exist
     */
    public function handleCallback(int $testResultId, int $httpStatus): array
    {
        Log::info('InsyteService@handleCallback: starting', [
            'test_result_id' => $testResultId,
            'http_status' => $httpStatus,
        ]);

        $testResult = TestResult::findOrFail($testResultId);
        $outcome = $this->outcomeFor($httpStatus);
        $deliveryStatus = $this->deliveryStatusFor($outcome);
        $ignored = $outcome === self::OUTCOME_COMPLETED && $this->isTestingPeriod();
        $markReviewed = $outcome === self::OUTCOME_COMPLETED && ! $ignored && ! $testResult->is_reviewed;

        try {
            DB::beginTransaction();

            if ($deliveryStatus !== null) {
                MyHealthFeedDelivery::updateOrCreate(
                    ['test_result_id' => $testResultId],
                    ['status' => $deliveryStatus, 'http_status' => $httpStatus]
                );
            }

            if ($markReviewed) {
                $testResult->is_reviewed = true;
                $testResult->save();
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('InsyteService@handleCallback: failed', [
                'test_result_id' => $testResultId,
                'http_status' => $httpStatus,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        $result = [
            'test_result_id' => $testResultId,
            'http_status' => $httpStatus,
            'outcome' => $outcome,
            'delivery_status' => $deliveryStatus,
            'is_reviewed' => (bool) $testResult->is_reviewed,
            'ignored' => $ignored,
        ];

        // Non-completed callbacks at error level so they stay visible under production LOG_LEVEL=error
        $outcome === self::OUTCOME_COMPLETED || $outcome === self::OUTCOME_PROCESSING
            ? Log::info('InsyteService@handleCallback: completed', $result)
            : Log::error('InsyteService@handleCallback: Insyte reported the feed was not processed', $result);

        return $result;
    }

    public function outcomeFor(int $httpStatus): string
    {
        return match (true) {
            $httpStatus === 202 => self::OUTCOME_PROCESSING,
            $httpStatus === 409, $httpStatus >= 200 && $httpStatus < 300 => self::OUTCOME_COMPLETED,
            $httpStatus >= 400 && $httpStatus < 500 => self::OUTCOME_REJECTED,
            default => self::OUTCOME_FAILED,
        };
    }

    protected function deliveryStatusFor(string $outcome): ?string
    {
        return match ($outcome) {
            self::OUTCOME_COMPLETED => MyHealthFeedDelivery::STATUS_SENT,
            self::OUTCOME_REJECTED => MyHealthFeedDelivery::STATUS_SKIPPED,
            self::OUTCOME_FAILED => MyHealthFeedDelivery::STATUS_FAILED,
            default => null,
        };
    }

    /**
     * True before Insyte (MyHealth feed) replaces the AI review. During that period
     * the AI review owns is_reviewed and Insyte callbacks must not change it.
     */
    public function isTestingPeriod(): bool
    {
        return now()->lt(Carbon::parse(SendToAIServer::MYHEALTH_FEED_FROM)->startOfDay());
    }
}
