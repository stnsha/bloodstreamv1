<?php

namespace App\Jobs;

use App\Models\AIError;
use App\Models\AIReview;
use App\Services\AIApiClient;
use App\Services\ApiTokenService;
use App\Services\TestResultCompilerService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SendToAIServer implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;  // HTTP send only, not processing
    public $tries = 1;     // Can retry failed sends
    public $queue = 'ai-reviews';  // Dedicated queue for AI review dispatch
    public $testResultId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $testResultId)
    {
        $this->testResultId = $testResultId;
    }

    /**
     * Execute the job.
     */
    public function handle(
        TestResultCompilerService $compiler,
        AIApiClient $apiClient,
        ApiTokenService $apiTokenService
    ): void {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        try {
            // Get authentication token
            $token = $apiTokenService->getValidToken();

            if (!$token) {
                throw new RuntimeException('Failed to obtain AI service token');
            }

            // Fetch and compile test result data
            $testResult = $compiler->fetchTestResult($this->testResultId);
            $compiledData = $compiler->compileTestResultData($testResult, 'MHJOB');

            // Create ai_reviews record with pending status
            $aiReview = DB::transaction(function () use ($compiledData) {
                return AIReview::create([
                    'test_result_id' => $this->testResultId,
                    'processing_status' => 'PENDING',
                    'compiled_results' => $compiledData,
                    'ai_response' => null,
                    'raw_response' => null,
                ]);
            });

            // Build payload with callback URL
            $payload = array_merge([
                'test_result_id' => $this->testResultId,
                'source' => 'MHJOB',
            ], $compiledData);

            // Send to AI server asynchronously and capture response
            $responseData = $apiClient->sendAsync($payload, $token);
            // Log::info('responseData from sendAsync', [
            //     'responseData' => $responseData
            // ]);

            // Check AI server response
            if (isset($responseData)) {
                // Check if AI server returned failure
                if (isset($responseData['success']) && $responseData['success'] === false) {
                    // Hard delete AIReview and create AIError
                    $aiReview->forceDelete();

                    AIError::create([
                        'test_result_id' => $this->testResultId,
                        'processing_status' => 'FAILED',
                        'http_status' => 500,
                        'error_message' => $responseData['message'] ?? $responseData['error'] ?? 'AI server rejected request',
                        'compiled_data' => $compiledData,
                        'attempt_count' => $this->attempts()
                    ]);

                    // Don't throw exception - no retry needed for queue full, etc.
                    return;
                }

                // Success - update AIReview with status from server (QUEUED, PROCESSING, etc.)
                $aiReview->processing_status = $responseData['status'];
                $aiReview->save();

                // Log performance metrics
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

            // Update ai_reviews status to failed or create error record
            $this->handleError($e);

            // Re-throw to allow job retry
            throw $e;
        }
    }

    /**
     * Handle job failure
     */
    public function failed(Exception $exception): void
    {
        // Hard delete AIReview record and create error record
        DB::transaction(function () use ($exception) {
            $aiReview = AIReview::where('test_result_id', $this->testResultId)
                ->orderBy('id', 'desc')
                ->first();

            if ($aiReview) {
                $aiReview->forceDelete();
            }

            // Create error record
            $this->storeError($exception);
        });
    }

    /**
     * Handle error during processing
     */
    protected function handleError(Exception $e): void
    {
        try {
            DB::transaction(function () use ($e) {
                // Hard delete AIReview record if exists
                $aiReview = AIReview::where('test_result_id', $this->testResultId)
                    ->orderBy('id', 'desc')
                    ->first();

                if ($aiReview) {
                    $aiReview->forceDelete();
                }

                // ALWAYS store error to ai_errors table
                $this->storeError($e);
            });
        } catch (Exception $dbError) {
            // Silently fail - error storage is best effort
        }
    }

    /**
     * Store error to ai_errors table
     */
    protected function storeError(Exception $e): void
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
                'compiled_data' => $compiledData,
                'attempt_count' => $this->attempts()
            ]);
        } catch (Exception $dbError) {
            // Silently fail - error storage is best effort
        }
    }
}