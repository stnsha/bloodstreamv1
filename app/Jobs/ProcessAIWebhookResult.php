<?php

namespace App\Jobs;

use App\Models\AIError;
use App\Models\AIReview;
use App\Models\TestResult;
use App\Services\ReviewHtmlGenerator;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessAIWebhookResult implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;  // Fast DB operation
    public $tries = 1;     // Webhook already acknowledged, don't retry
    protected $webhookData;

    /**
     * Create a new job instance.
     */
    public function __construct(array $webhookData)
    {
        $this->webhookData = $webhookData;
        $this->onQueue('ai-webhooks');  // Dedicated queue for time-sensitive webhook responses
    }

    /**
     * Execute the job.
     */
    public function handle(ReviewHtmlGenerator $htmlGenerator): void
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        $testResultId = $this->webhookData['test_result_id'];

        Log::channel('webhook')->info('ProcessAIWebhookResult job started', [
            'test_result_id' => $testResultId,
            'success' => $this->webhookData['success'],
            'status' => $this->webhookData['status']
        ]);

        try {
            // Check if webhook indicates success
            if (!$this->webhookData['success'] || $this->webhookData['status'] !== 'DONE') {
                throw new Exception('Webhook indicates AI processing failed: ' . json_encode($this->webhookData));
            }

            // Extract AI analysis data
            $aiAnalysis = $this->webhookData['data']['ai_analysis'];

            // Convert AI response to HTML
            $htmlReview = $htmlGenerator->convertToHtml($aiAnalysis['answer']);

            // Update ai_reviews and test_result in transaction
            DB::transaction(function () use ($testResultId, $aiAnalysis, $htmlReview) {
                // Single optimized query - prioritize PENDING status (was 2 queries, now 1)
                $aiReview = AIReview::where('test_result_id', $testResultId)
                    ->orderByRaw("FIELD(processing_status, 'PENDING', 'COMPLETED', 'FAILED')")
                    ->orderBy('id', 'desc')
                    ->first();

                $wasPending = $aiReview?->processing_status === 'PENDING';

                if (!$aiReview) {
                    // Log warning
                    Log::channel('webhook')->warning('No AI review record found for webhook - storing to ai_errors', [
                        'test_result_id' => $testResultId,
                    ]);

                    // Store webhook data to ai_errors table for recovery
                    AIError::create([
                        'test_result_id' => $testResultId,
                        'processing_status' => 'FAILED',
                        'http_status' => $aiAnalysis['status'] ?? 200,
                        'error_message' => 'Webhook received but AIReview record not found in database',
                        'compiled_data' => $this->webhookData,
                        'attempt_count' => 1,
                    ]);

                    return;
                }

                if (!$wasPending) {
                    Log::channel('webhook')->warning('AI review not pending', [
                        'test_result_id' => $testResultId,
                        'status' => $aiReview->processing_status,
                    ]);
                }

                // Update record using model (respects casts and preserves compiled_results)
                $aiReview->processing_status = 'COMPLETED';
                $aiReview->http_status = $aiAnalysis['status'];
                $aiReview->ai_response = $htmlReview;
                $aiReview->raw_response = $aiAnalysis;
                // Preserve existing compiled_results
                $aiReview->save();

                // Update test_result.is_reviewed flag
                $testResult = TestResult::find($testResultId);
                if ($testResult) {
                    $testResult->is_reviewed = true;
                    $testResult->save();

                    Log::channel('webhook')->info('Test result marked as reviewed', [
                        'test_result_id' => $testResultId
                    ]);
                }
            });

            Log::channel('webhook')->info('AI review stored successfully from webhook', [
                'test_result_id' => $testResultId
            ]);

            // Log performance metrics
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $memoryUsed = round((memory_get_usage() - $startMemory) / 1024 / 1024, 2);

            Log::channel('performance')->info('AI webhook job completed', [
                'test_result_id' => $testResultId,
                'duration_ms' => $duration,
                'memory_mb' => $memoryUsed,
                'attempt' => $this->attempts(),
            ]);

        } catch (Exception $e) {
            Log::channel('webhook')->error('ProcessAIWebhookResult job failed', [
                'test_result_id' => $testResultId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Update ai_reviews status to failed and store error
            $this->handleError($testResultId, $e);

            // Don't rethrow - webhook already acknowledged
        }
    }

    /**
     * Handle job failure
     */
    public function failed(Exception $exception): void
    {
        $testResultId = $this->webhookData['test_result_id'] ?? null;

        Log::channel('webhook')->error('ProcessAIWebhookResult job failed permanently', [
            'test_result_id' => $testResultId,
            'error' => $exception->getMessage()
        ]);

        if ($testResultId) {
            $this->handleError($testResultId, $exception);
        }
    }

    /**
     * Handle error by updating ai_reviews and creating error record
     */
    protected function handleError(int $testResultId, Exception $e): void
    {
        try {
            DB::transaction(function () use ($testResultId, $e) {
                // Hard delete AIReview record if exists
                $aiReview = AIReview::where('test_result_id', $testResultId)
                    ->orderBy('id', 'desc')
                    ->first();

                if ($aiReview) {
                    $aiReview->forceDelete();
                }

                // Create error record
                AIError::create([
                    'test_result_id' => $testResultId,
                    'error_message' => $e->getMessage(),
                    'compiled_data' => $this->webhookData,
                    'attempt_count' => 1
                ]);
            });
        } catch (Exception $dbError) {
            Log::channel('webhook')->error('Failed to handle webhook error', [
                'test_result_id' => $testResultId,
                'error' => $dbError->getMessage()
            ]);
        }
    }
}