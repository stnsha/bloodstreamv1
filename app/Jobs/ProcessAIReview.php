<?php

namespace App\Jobs;

use App\Services\AIReviewService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAIReview implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 180; // AI request is 120s + buffer
    public $tries = 1;     // Single attempt (errors stored in ai_errors table)
    public $testResultId;

    public function __construct(int $testResultId)
    {
        $this->testResultId = $testResultId;
    }

    public function handle(AIReviewService $aiReviewService): void
    {
        Log::info('AI review job started', [
            'test_result_id' => $this->testResultId,
            'job_id' => $this->job->getJobId() ?? 'sync'
        ]);

        try {
            $result = $aiReviewService->processSingle($this->testResultId, 'MyHealth Job');

            if ($result->isSuccessful()) {
                Log::info('AI review job completed successfully', [
                    'test_result_id' => $this->testResultId
                ]);
            } else {
                Log::warning('AI review job completed with error', [
                    'test_result_id' => $this->testResultId,
                    'error' => $result->errorMessage
                ]);
            }
        } catch (Exception $e) {
            Log::error('AI review job failed', [
                'test_result_id' => $this->testResultId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function failed(Exception $exception): void
    {
        Log::error('AI review job failed permanently', [
            'test_result_id' => $this->testResultId,
            'error' => $exception->getMessage()
        ]);
    }
}