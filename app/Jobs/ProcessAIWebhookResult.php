<?php

namespace App\Jobs;

use App\Models\AIError;
use App\Models\AIReview;
use App\Models\TestResult;
use App\Services\ReviewHtmlGenerator;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessAIWebhookResult implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;  // Fast DB operation

    public $tries = 3;     // Allow retries - webhook acknowledgment is separate from processing

    public $backoff = [60, 300, 900];  // Exponential backoff: 1 min, 5 min, 15 min

    public $uniqueFor = 3600;  // Lock for 1 hour

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
     * Get unique ID for this webhook
     * Generates idempotency key from webhook payload hash (system-generated, not from webhook data)
     */
    public function uniqueId(): string
    {
        // Generate deterministic hash from webhook payload
        // Same webhook payload will always produce same hash
        $idempotencyKey = hash('sha256', json_encode($this->webhookData));

        return "process_ai_webhook_{$idempotencyKey}";
    }

    /**
     * Execute the job.
     * Safe to retry - includes idempotency checks and non-destructive error handling
     */
    public function handle(ReviewHtmlGenerator $htmlGenerator): void
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        $testResultId = $this->webhookData['test_result_id'];

        // Generate deterministic idempotency key from webhook payload
        // Our system generates this (not from webhook data), so identical webhook payloads
        // will always produce the same key, preventing duplicate processing
        $idempotencyKey = hash('sha256', json_encode($this->webhookData));

        Log::channel('webhook')->info('ProcessAIWebhookResult job started', [
            'test_result_id' => $testResultId,
            'idempotency_key' => $idempotencyKey,
            'success' => $this->webhookData['success'],
            'status' => $this->webhookData['status'],
        ]);

        try {
            // Check if webhook indicates success
            if (! $this->webhookData['success'] || $this->webhookData['status'] !== 'DONE') {
                throw new Exception('Webhook indicates AI processing failed: '.json_encode($this->webhookData));
            }

            // Extract AI analysis data
            $aiAnalysis = $this->webhookData['data']['ai_analysis'];

            // Convert AI response to HTML
            $htmlReview = $htmlGenerator->convertToHtml($aiAnalysis['answer']);

            // Update ai_reviews and test_result in transaction
            DB::transaction(function () use ($testResultId, $aiAnalysis, $htmlReview, $idempotencyKey) {
                // Get most recent AIReview record for this test result (including soft-deleted)
                $aiReview = AIReview::where('test_result_id', $testResultId)
                    ->withTrashed()
                    ->orderBy('id', 'desc')
                    ->first();

                if (! $aiReview) {
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

                // IDEMPOTENCY CHECK: Skip if already processed with same webhook
                if ($aiReview->webhook_idempotency_key === $idempotencyKey && $aiReview->processing_status === 'COMPLETED') {
                    Log::channel('webhook')->info('Webhook already processed, skipping', [
                        'test_result_id' => $testResultId,
                        'idempotency_key' => $idempotencyKey,
                    ]);

                    return;
                }

                // Update record using model (respects casts and preserves compiled_results)
                $aiReview->processing_status = 'COMPLETED';
                $aiReview->http_status = $aiAnalysis['status'];
                $aiReview->ai_response = $htmlReview;
                $aiReview->raw_response = $aiAnalysis;
                $aiReview->webhook_idempotency_key = $idempotencyKey;
                // Preserve existing compiled_results
                $aiReview->save();

                // Update test_result.is_reviewed flag (only on first successful webhook)
                $testResult = TestResult::find($testResultId);
                if ($testResult && ! $testResult->is_reviewed) {
                    $testResult->is_reviewed = true;
                    $testResult->save();

                    Log::channel('webhook')->info('Test result marked as reviewed', [
                        'test_result_id' => $testResultId,
                    ]);
                }
            });

            Log::channel('webhook')->info('AI review stored successfully from webhook', [
                'test_result_id' => $testResultId,
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
                'trace' => $e->getTraceAsString(),
            ]);

            // Update ai_reviews status to failed and store error
            $this->handleError($testResultId, $e);

            // Re-throw to allow retries with backoff
            throw $e;
        }
    }

    /**
     * Handle permanent job failure after all retries exhausted
     */
    public function failed(Exception $exception): void
    {
        $testResultId = $this->webhookData['test_result_id'] ?? null;

        Log::channel('webhook')->error('ProcessAIWebhookResult job failed permanently after all retries', [
            'test_result_id' => $testResultId,
            'error' => $exception->getMessage(),
        ]);

        if ($testResultId) {
            $this->handleError($testResultId, $exception);
        }
    }

    /**
     * Handle error by updating ai_reviews and creating error record
     * Does not delete - preserves audit trail for manual recovery
     */
    protected function handleError(int $testResultId, Exception $e): void
    {
        try {
            DB::transaction(function () use ($testResultId, $e) {
                // Update AIReview status to FAILED instead of deleting (preserves audit trail)
                $aiReview = AIReview::where('test_result_id', $testResultId)
                    ->withTrashed()
                    ->orderBy('id', 'desc')
                    ->first();

                if ($aiReview && $aiReview->processing_status !== 'COMPLETED') {
                    $aiReview->update([
                        'processing_status' => 'FAILED',
                        'raw_response' => json_encode([
                            'error' => $e->getMessage(),
                            'error_class' => get_class($e),
                        ]),
                    ]);
                }

                // Create error record for recovery
                AIError::create([
                    'test_result_id' => $testResultId,
                    'processing_status' => 'FAILED',
                    'error_message' => $e->getMessage(),
                    'error_trace' => $e->getTraceAsString(),
                    'compiled_data' => $this->webhookData,
                    'attempt_count' => 1,
                ]);
            });
        } catch (Exception $dbError) {
            Log::channel('webhook')->error('Failed to handle webhook error', [
                'test_result_id' => $testResultId,
                'original_error' => $e->getMessage(),
                'storage_error' => $dbError->getMessage(),
            ]);
        }
    }
}
