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
    }

    /**
     * Execute the job.
     */
    public function handle(ReviewHtmlGenerator $htmlGenerator): void
    {
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
                // Find the pending ai_reviews record
                $aiReview = AIReview::where('test_result_id', $testResultId)
                    ->where('processing_status', 'PENDING')
                    ->orderBy('id', 'desc')
                    ->first();

                if (!$aiReview) {
                    // If no pending record found, check if any record exists
                    Log::channel('webhook')->warning('No pending ai_reviews record found, checking for existing record', [
                        'test_result_id' => $testResultId
                    ]);

                    $existingReview = AIReview::where('test_result_id', $testResultId)->first();

                    if ($existingReview) {
                        // Update existing record using model (respects casts and preserves compiled_results)
                        $existingReview->processing_status = 'COMPLETED';
                        $existingReview->http_status = $aiAnalysis['status'];
                        $existingReview->ai_response = $htmlReview;
                        $existingReview->raw_response = $aiAnalysis;
                        // Preserve existing compiled_results
                        $existingReview->save();
                    } else {
                        // No existing record - create new one
                        AIReview::create([
                            'test_result_id' => $testResultId,
                            'processing_status' => 'COMPLETED',
                            'http_status' => $aiAnalysis['status'],
                            'ai_response' => $htmlReview,
                            'raw_response' => $aiAnalysis,
                            'compiled_results' => []
                        ]);
                    }
                } else {
                    // Update existing pending record using model (respects casts and preserves compiled_results)
                    $aiReview->processing_status = 'COMPLETED';
                    $aiReview->http_status = $aiAnalysis['status'];
                    $aiReview->ai_response = $htmlReview;
                    $aiReview->raw_response = $aiAnalysis;
                    // Preserve existing compiled_results
                    $aiReview->save();
                }

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