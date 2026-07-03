<?php

namespace App\Jobs;

use App\Models\AIError;
use App\Models\AIReview;
use App\Models\TestResult;
use App\Services\ReviewHtmlGenerator;
use App\Services\TestResultCompilerService;
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
    public function handle(ReviewHtmlGenerator $htmlGenerator, TestResultCompilerService $compiler): void
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
            DB::transaction(function () use ($testResultId, $aiAnalysis, $htmlReview, $idempotencyKey, $compiler) {
                // Get most recent AIReview record for this test result (including soft-deleted)
                $aiReview = AIReview::where('test_result_id', $testResultId)
                    ->withTrashed()
                    ->orderBy('id', 'desc')
                    ->first();

                if ($aiReview && $aiReview->trashed()) {
                    // Row was soft-deleted after an earlier local failure, but the AI service had
                    // already accepted the request and is now completing it. Restore it so its
                    // original compiled_results (untouched by the soft delete) is kept intact.
                    Log::channel('webhook')->info('Restoring soft-deleted AI review record for webhook', [
                        'test_result_id' => $testResultId,
                        'ai_review_id' => $aiReview->id,
                    ]);

                    $aiReview->deleted_at = null;
                } elseif (! $aiReview) {
                    // No prior record at all (e.g. manual cleanup) — recompile compiled_results
                    // so the NOT NULL column is still satisfied with real data.
                    Log::channel('webhook')->info('No prior AI review record found for webhook - recompiling compiled_results', [
                        'test_result_id' => $testResultId,
                    ]);

                    $aiReview = new AIReview;
                    $aiReview->test_result_id = $testResultId;
                    $aiReview->compiled_results = $this->recompileResults($testResultId, $compiler);
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
     * Handle permanent job failure after all retries exhausted.
     * Removes any non-COMPLETED ai_review record (force- or soft-deleted, see handleError())
     * so the result can be re-dispatched.
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
     * Handle error by removing any non-COMPLETED ai_review record and creating an ai_errors entry.
     *
     * If the webhook itself reported a confirmed failure (processing_status FAILED / non-DONE),
     * the AI service has already given its final verdict — no future webhook will complete this
     * request, so the record is force-deleted. For any other error (our own bug, DB failure, etc.)
     * the AI service may still complete the request asynchronously, so the record is soft-deleted
     * instead, allowing a later webhook to find and restore it.
     */
    protected function handleError(int $testResultId, Exception $e): void
    {
        $isConfirmedAiFailure = $this->isConfirmedFailureWebhook();

        try {
            DB::transaction(function () use ($testResultId, $e, $isConfirmedAiFailure) {
                $query = AIReview::where('test_result_id', $testResultId)
                    ->where('processing_status', '!=', 'COMPLETED');

                if ($isConfirmedAiFailure) {
                    $query->forceDelete();
                } else {
                    $query->delete();
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

    /**
     * Whether the incoming webhook payload itself reported a confirmed processing failure
     * (success = false, or status/processing_status not DONE) rather than some other local error.
     */
    private function isConfirmedFailureWebhook(): bool
    {
        return ! ($this->webhookData['success'] ?? true) || ($this->webhookData['status'] ?? null) !== 'DONE';
    }

    /**
     * Recompile compiled_results from scratch for the rare case where no AIReview row
     * (not even a soft-deleted one) exists for this test result. Mirrors the same calls
     * SendToAIServer::handle() makes, so the recompiled data matches what would have
     * originally been sent to the AI service.
     */
    protected function recompileResults(int $testResultId, TestResultCompilerService $compiler): array
    {
        $testResult = $compiler->fetchTestResult($testResultId);
        $testResult = $compiler->ensureSpecialTestsCalculated($testResult);

        return $compiler->compileTestResultData($testResult, 'MHJOB');
    }
}
