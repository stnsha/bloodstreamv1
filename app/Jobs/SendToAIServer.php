<?php

namespace App\Jobs;

use App\Models\AIError;
use App\Models\AIReview;
use App\Models\ARR\ARREnrollment;
use App\Models\TestResult;
use App\Services\AIApiClient;
use App\Services\ApiTokenService;
use App\Services\PanelCompletenessService;
use App\Services\TestResultCompilerService;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SendToAIServer implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;  // 120 seconds for AI service communication

    public $tries = 1;      // Single attempt - no job-level retry. A failure is
                             // recorded (ai_errors) and left for the scheduled
                             // sweep/retry commands to pick back up, rather than
                             // retrying inside this job.

    public $testResultId;

    public $uniqueFor = 3600;  // Lock job uniqueness for 1 hour

    /**
     * On or after this date (server time, 00:00), a result ready for review is
     * sent to the MyHealth lab feed (SendToMyHealthFeed) instead of the AI server.
     */
    const MYHEALTH_FEED_FROM = '2026-11-13';

    /**
     * Testing overlap: from this date (server time, 00:00) until MYHEALTH_FEED_FROM,
     * the AI review runs exactly as before and, once the AI server has accepted the
     * request, a copy is also sent to the MyHealth feed. The AI review keeps
     * priority: the feed copy never changes is_reviewed and its failure never
     * affects the AI review.
     */
    const MYHEALTH_FEED_TEST_FROM = '2026-11-01';

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
     * Single attempt - idempotency checks guard against being dispatched twice
     * for the same test result, but a failure here is not retried in-job
     */
    public function handle(
        TestResultCompilerService $compiler,
        AIApiClient $apiClient,
        ApiTokenService $apiTokenService,
        PanelCompletenessService $panelCompletenessService
    ): void {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        try {
            // Early check: Verify test result exists and is not already reviewed
            $testResultCheck = TestResult::find($this->testResultId);

            if (! $testResultCheck) {
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

            if (! $testResultCheck->is_completed) {
                Log::channel('performance')->info('SendToAIServer: Test result not completed, skipping', [
                    'test_result_id' => $this->testResultId,
                ]);

                return;
            }

            // Held for consult call release (enrolled result): only the release in
            // ConsultCallController::dispatchReleasedAiReview, which stamps
            // ai_review_released_at first, may send it. Guards every other
            // dispatch path (scheduled sweepers, retry/requeue commands).
            if ($testResultCheck->isAiReviewHeld()) {
                Log::channel('performance')->info('SendToAIServer: AI review held for consult call release, skipping', [
                    'test_result_id' => $this->testResultId,
                ]);

                return;
            }

            // ARR-enrolled result: no AI review or MyHealth feed. Guards every
            // dispatch path (sweepers, retry/requeue commands).
            if (ARREnrollment::existsForTestResult($this->testResultId)) {
                Log::channel('performance')->info('SendToAIServer: test result enrolled in ARR, skipping', [
                    'test_result_id' => $this->testResultId,
                ]);

                return;
            }

            if (! $panelCompletenessService->checkAndHandle($testResultCheck)) {
                Log::channel('performance')->warning('SendToAIServer: incomplete panel data, is_completed reverted, skipping', [
                    'test_result_id' => $this->testResultId,
                ]);

                return;
            }

            // IDEMPOTENCY CHECK: Skip if already COMPLETED
            $existingReview = AIReview::where('test_result_id', $this->testResultId)
                ->where('processing_status', 'COMPLETED')
                ->first();

            if ($existingReview) {
                Log::channel('performance')->info('SendToAIServer: AI review already completed, skipping', [
                    'test_result_id' => $this->testResultId,
                ]);

                return;
            }

            // IN-FLIGHT CHECK: Skip if a send is already out awaiting its webhook.
            // ProcessPanelResults calls TestResultCompletionDispatcher on every
            // incremental delivery batch while is_reviewed is still false (which
            // stays false until the webhook completes it) - without this, a
            // slow-to-respond AI server (queue congestion) means every later batch
            // re-triggers a fresh send for the same test result before the first
            // one's webhook ever comes back.
            $pendingReview = AIReview::where('test_result_id', $this->testResultId)
                ->where('processing_status', 'PENDING')
                ->first();

            if ($pendingReview) {
                Log::channel('performance')->info('SendToAIServer: AI review already PENDING (in flight), skipping', [
                    'test_result_id' => $this->testResultId,
                    'ai_review_id' => $pendingReview->id,
                ]);

                return;
            }

            // From MYHEALTH_FEED_FROM the MyHealth lab feed replaces the AI review.
            // Gated here, after the hold / completeness / idempotency checks, so
            // every dispatch path (completion, consult call release, sweepers,
            // retry/requeue commands) switches over together.
            if (now()->gte(Carbon::parse(self::MYHEALTH_FEED_FROM)->startOfDay())) {
                SendToMyHealthFeed::dispatch($this->testResultId);

                Log::channel('myhealth-feed')->info('SendToAIServer: routed to MyHealth feed instead of AI review', [
                    'test_result_id' => $this->testResultId,
                ]);

                return;
            }

            // Get authentication token
            $token = $apiTokenService->getValidToken();

            if (! $token) {
                throw new RuntimeException('Failed to obtain AI service token');
            }

            // Fetch and compile test result data
            $testResult = $compiler->fetchTestResult($this->testResultId);

            // Recalculate special tests if missing or all values are null before compiling for AI dispatch
            $testResult = $compiler->ensureSpecialTestsCalculated($testResult);

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
                    // Mark the pending record SUPERSEDED — non-COMPLETED rows must stay out of
                    // default "only COMPLETED rows" queries, but a late webhook can still find
                    // and complete it
                    AIReview::where('test_result_id', $this->testResultId)
                        ->where('processing_status', '!=', 'COMPLETED')
                        ->update(['processing_status' => 'SUPERSEDED']);

                    AIError::create([
                        'test_result_id' => $this->testResultId,
                        'processing_status' => 'FAILED',
                        'http_status' => 500,
                        'error_message' => $responseData['message'] ?? $responseData['error'] ?? 'AI server rejected request',
                        'compiled_data' => $compiledData,
                        'attempt_count' => $this->attempts(),
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

                $this->dispatchMyHealthFeedTestCopy();
            }
        } catch (Exception $e) {
            // Single attempt: record the failure and stop. Do NOT rethrow - this
            // job must complete "successfully" from Laravel's point of view so no
            // failed_jobs row is created (retry decisions live in ai_reviews/
            // ai_errors, not in the queue's own failure bookkeeping).
            $this->handleError($e);
        }
    }

    /**
     * Testing overlap (MYHEALTH_FEED_TEST_FROM until MYHEALTH_FEED_FROM): send a copy
     * of an accepted AI review request to the MyHealth feed. markReviewed = false, so
     * is_reviewed stays owned by the AI review webhook. Any failure is logged and
     * swallowed so the AI review is never affected.
     */
    protected function dispatchMyHealthFeedTestCopy(): void
    {
        if (now()->lt(Carbon::parse(self::MYHEALTH_FEED_TEST_FROM)->startOfDay())) {
            return;
        }

        try {
            SendToMyHealthFeed::dispatch($this->testResultId, false);

            Log::channel('myhealth-feed')->info('SendToAIServer: testing overlap, MyHealth feed copy dispatched alongside AI review', [
                'test_result_id' => $this->testResultId,
            ]);
        } catch (Throwable $e) {
            Log::channel('myhealth-feed')->error('SendToAIServer: testing overlap, failed to dispatch MyHealth feed copy', [
                'test_result_id' => $this->testResultId,
                'error' => $e->getMessage(),
            ]);
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
                // Mark any non-COMPLETED record SUPERSEDED — hidden from default "only COMPLETED
                // rows" queries, but a late webhook can still find and complete it
                AIReview::where('test_result_id', $this->testResultId)
                    ->where('processing_status', '!=', 'COMPLETED')
                    ->update(['processing_status' => 'SUPERSEDED']);

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
                // Mark any non-COMPLETED record SUPERSEDED — hidden from default "only COMPLETED
                // rows" queries, but a late webhook can still find and complete it
                AIReview::where('test_result_id', $this->testResultId)
                    ->where('processing_status', '!=', 'COMPLETED')
                    ->update(['processing_status' => 'SUPERSEDED']);

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
                'attempt_count' => $this->attempts(),
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
}
