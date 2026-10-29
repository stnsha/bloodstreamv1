<?php

namespace App\Services;

use App\Jobs\SendToAIServer;
use App\Models\TestResult;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class InsyteService
{
    /**
     * Mark a test result as reviewed once Insyte confirms it has been received.
     * Idempotent: a result that is already reviewed is left untouched.
     *
     * Before SendToAIServer::MYHEALTH_FEED_FROM (Insyte testing period) the AI
     * review owns is_reviewed, so the call is accepted but ignored (ignored = true)
     * and is_reviewed is not changed.
     *
     * @return array{test_result_id: int, is_reviewed: bool, already_reviewed: bool, ignored: bool}
     *
     * @throws ModelNotFoundException when the test result does not exist
     */
    public function markReviewed(int $testResultId): array
    {
        Log::info('InsyteService@markReviewed: starting', ['test_result_id' => $testResultId]);

        $testResult = TestResult::findOrFail($testResultId);

        if ($this->isTestingPeriod()) {
            Log::info('InsyteService@markReviewed: Insyte testing period, is_reviewed left to the AI review', [
                'test_result_id' => $testResultId,
                'is_reviewed' => (bool) $testResult->is_reviewed,
                'insyte_live_from' => SendToAIServer::MYHEALTH_FEED_FROM,
            ]);

            return [
                'test_result_id' => $testResultId,
                'is_reviewed' => (bool) $testResult->is_reviewed,
                'already_reviewed' => (bool) $testResult->is_reviewed,
                'ignored' => true,
            ];
        }

        if ($testResult->is_reviewed) {
            Log::info('InsyteService@markReviewed: already reviewed, skipping', ['test_result_id' => $testResultId]);

            return [
                'test_result_id' => $testResultId,
                'is_reviewed' => true,
                'already_reviewed' => true,
                'ignored' => false,
            ];
        }

        try {
            DB::beginTransaction();

            $testResult->is_reviewed = true;
            $testResult->save();

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('InsyteService@markReviewed: failed', [
                'test_result_id' => $testResultId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        Log::info('InsyteService@markReviewed: completed', ['test_result_id' => $testResultId]);

        return [
            'test_result_id' => $testResultId,
            'is_reviewed' => true,
            'already_reviewed' => false,
            'ignored' => false,
        ];
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
