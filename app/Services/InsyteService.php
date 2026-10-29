<?php

namespace App\Services;

use App\Models\TestResult;
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
     * @return array{test_result_id: int, is_reviewed: bool, already_reviewed: bool}
     *
     * @throws ModelNotFoundException when the test result does not exist
     */
    public function markReviewed(int $testResultId): array
    {
        Log::info('InsyteService@markReviewed: starting', ['test_result_id' => $testResultId]);

        $testResult = TestResult::findOrFail($testResultId);

        if ($testResult->is_reviewed) {
            Log::info('InsyteService@markReviewed: already reviewed, skipping', ['test_result_id' => $testResultId]);

            return [
                'test_result_id' => $testResultId,
                'is_reviewed' => true,
                'already_reviewed' => true,
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
        ];
    }
}
