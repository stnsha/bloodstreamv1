<?php

namespace App\Jobs;

use App\Models\AIError;
use App\Models\AIReview;
use App\Models\TestResult;
use App\Services\AIApiClient;
use App\Services\ApiTokenService;
use App\Services\TestResultCompilerService;
use Exception;
use Illuminate\Bus\Queueable;
use Throwable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SendToAIServer implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;  // 120 seconds for AI service communication
    public $tries = 6;      // Retry up to 6 times on failure
    public $backoff = [120, 300, 600, 900, 1200, 1800];  // 2min, 5min, 10min, 15min, 20min, 30min
    public $testResultId;
    public $uniqueFor = 3600;  // Lock job uniqueness for 1 hour

    /**
     * Create a new job instance.
     */
    public function __construct(int $testResultId)
    {
        $this->testResultId = $testResultId;
        $this->onQueue('ai-reviews');  // Dedicated queue for AI review dispatch
    }

    /**
     * Get the unique ID for the job
     * Prevents duplicate jobs for the same test result within 1 hour
     */
    public function uniqueId(): string
    {
        return "send_to_ai_server_{$this->testResultId}";
    }

    /**
     * Execute the job.
     * Safe to retry - includes idempotency checks and non-destructive error handling
     */
    public function handle(
        TestResultCompilerService $compiler,
        AIApiClient $apiClient,
        ApiTokenService $apiTokenService
    ): void {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        try {
            // Early check: Verify test result exists and is not already reviewed
            $testResultCheck = TestResult::find($this->testResultId);

            if (!$testResultCheck) {
                Log::channel('performance')->info('SendToAIServer: Test result not found, skipping', [
                    'test_result_id' => $this->testResultId,
                ]);
                return;
            }

            if ($testResultCheck->is_reviewed) {
                Log::channel('performance')->info('SendToAIServer: Test result already reviewed, skipping', [
                    'test_result_id' => $this->testResultId,
                ]);
                return;
            }

            if (!$testResultCheck->is_completed) {
                Log::channel('performance')->info('SendToAIServer: Test result not completed, skipping', [
                    'test_result_id' => $this->testResultId,
                ]);
                return;
            }

            // IDEMPOTENCY CHECK: Skip only if already COMPLETED
            $existingReview = AIReview::where('test_result_id', $this->testResultId)
                ->where('processing_status', 'COMPLETED')
                ->first();

            if ($existingReview) {
                $testResult = TestResult::find($this->testResultId);
                if ($testResult && !$testResult->is_reviewed) {
                    $testResult->is_reviewed = true;
                    $testResult->save();

                    Log::channel('performance')->info('Fixed is_reviewed flag for completed review', [
                        'test_result_id' => $this->testResultId,
                    ]);
                }

                Log::channel('performance')->info('SendToAIServer: AI review already completed, skipping', [
                    'test_result_id' => $this->testResultId,
                ]);
                return;
            }

            // Get authentication token
            $token = $apiTokenService->getValidToken();

            if (!$token) {
                throw new RuntimeException('Failed to obtain AI service token');
            }

            // Fetch and compile test result data
            $testResult = $compiler->fetchTestResult($this->testResultId);
            $compiledData = $compiler->compileTestResultData($testResult, 'MHJOB');

            // Create or update ai_reviews record with pending status (idempotent)
            $aiReview = DB::transaction(function () use ($compiledData) {
                return AIReview::updateOrCreate(
                    ['test_result_id' => $this->testResultId],
                    [
                        'processing_status' => 'PENDING',
                        'compiled_results' => $compiledData,
                        'ai_response' => null,
                        'raw_response' => null,
                    ]
                );
            });

            // Build payload with callback URL
            $payload = array_merge([
                'test_result_id' => $this->testResultId,
                'source' => 'MHJOB',
            ], $compiledData);

            // Send to AI server asynchronously and capture response
            $responseData = $apiClient->sendAsync($payload, $token);

            // Check AI server response
            if (isset($responseData)) {
                // Check if AI server returned failure
                if (isset($responseData['success']) && $responseData['success'] === false) {
                    // Delete the pending record — ai_reviews must only contain COMPLETED rows
                    AIReview::where('test_result_id', $this->testResultId)
                        ->where('processing_status', '!=', 'COMPLETED')
                        ->forceDelete();

                    AIError::create([
                        'test_result_id' => $this->testResultId,
                        'processing_status' => 'FAILED',
                        'http_status' => 500,
                        'error_message' => $responseData['message'] ?? $responseData['error'] ?? 'AI server rejected request',
                        'compiled_data' => $compiledData,
                        'attempt_count' => $this->attempts()
                    ]);

                    // Don't throw exception - server explicitly rejected, no retry needed
                    return;
                }

                // Success - AI server accepted the request; keep record as PENDING until webhook completes it
                $duration = round((microtime(true) - $startTime) * 1000, 2);
                $memoryUsed = round((memory_get_usage() - $startMemory) / 1024 / 1024, 2);

                Log::channel('performance')->info('AI review sent successfully', [
                    'test_result_id' => $this->testResultId,
                    'duration_ms' => $duration,
                    'memory_mb' => $memoryUsed,
                    'attempt' => $this->attempts(),
                ]);
            }
        } catch (Exception $e) {
            // Detect 429/QUEUE_FULL: release back to queue without polluting ai_errors
            if ($this->isQueueFullError($e) && $this->attempts() < $this->tries) {
                $delay = $this->backoff[$this->attempts() - 1] ?? end($this->backoff);

                Log::channel('job')->warning('SendToAIServer: AI queue full, releasing for retry', [
                    'test_result_id' => $this->testResultId,
                    'attempt' => $this->attempts(),
                    'max_tries' => $this->tries,
                    'retry_delay_seconds' => $delay,
                ]);

                    $this->release($delay);
                return;
            }

            // Non-429 errors or final 429 attempt: record error and re-throw
            $this->handleError($e);

            throw $e;
        }
    }

    /**
     * Handle permanent job failure after all retries exhausted
     * Updates AI review status and logs error for manual recovery
     */
    public function failed(Throwable $exception): void
    {
        try {
            DB::transaction(function () use ($exception) {
                // Delete any non-COMPLETED record — ai_reviews must only contain COMPLETED rows
                AIReview::where('test_result_id', $this->testResultId)
                    ->where('processing_status', '!=', 'COMPLETED')
                    ->forceDelete();

                // Store error record for recovery
                $this->storeError($exception);
            });
        } catch (Exception $dbError) {
            Log::error('SendToAIServer: Failed to record job failure', [
                'test_result_id' => $this->testResultId,
                'error' => $dbError->getMessage(),
            ]);
        }
    }

    /**
     * Handle error during processing
     * Updates AI review status and creates error record without deleting data
     */
    protected function handleError(Throwable $e): void
    {
        try {
            DB::transaction(function () use ($e) {
                // Delete any non-COMPLETED record — ai_reviews must only contain COMPLETED rows
                AIReview::where('test_result_id', $this->testResultId)
                    ->where('processing_status', '!=', 'COMPLETED')
                    ->forceDelete();

                // Store error to ai_errors table for recovery
                $this->storeError($e);
            });
        } catch (Exception $dbError) {
            Log::error('SendToAIServer: handleError failed to update records', [
                'test_result_id' => $this->testResultId,
                'error' => $dbError->getMessage(),
            ]);
        }
    }

    /**
     * Store error to ai_errors table for manual recovery
     */
    protected function storeError(Throwable $e): void
    {
        try {
            // Get compiled_data from ai_reviews if exists
            $compiledData = null;
            $aiReview = AIReview::where('test_result_id', $this->testResultId)
                ->orderBy('id', 'desc')
                ->first();

            if ($aiReview && $aiReview->compiled_results) {
                $compiledData = $aiReview->compiled_results;
            }

            AIError::create([
                'test_result_id' => $this->testResultId,
                'processing_status' => 'FAILED',
                'http_status' => 500,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'compiled_data' => $compiledData,
                'attempt_count' => $this->attempts()
            ]);

            Log::channel('job')->warning('SendToAIServer: Error recorded', [
                'test_result_id' => $this->testResultId,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);
        } catch (Exception $dbError) {
            // Silently fail - error storage is best effort
            Log::error('SendToAIServer: Failed to store error record', [
                'test_result_id' => $this->testResultId,
                'original_error' => $e->getMessage(),
                'storage_error' => $dbError->getMessage(),
            ]);
        }
    }

    /**
     * Check if the exception indicates a 429/QUEUE_FULL response from the AI server
     */
    protected function isQueueFullError(Throwable $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, '429') && (
            str_contains($message, 'QUEUE_FULL') || str_contains($message, 'Queue is full')
        );
    }
}