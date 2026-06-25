<?php

namespace App\Console\Commands;

use App\Jobs\SendToAIServer;
use App\Models\AIError;
use App\Models\AIReview;
use App\Models\TestResult;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RetryFailedAIReviews extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:retry-failed-reviews {--hours=24 : Retry errors from the last N hours} {--limit=100 : Maximum number of errors to retry}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retry failed AI reviews from the ai_errors table';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $hours = $this->option('hours');
        $limit = $this->option('limit');

        $this->info("Retrying failed AI reviews from the last {$hours} hours (max {$limit})...");

        try {
            // Find failed AI reviews from recent hours
            $failedErrors = AIError::where('processing_status', 'FAILED')
                ->where('created_at', '>=', now()->subHours($hours))
                ->where('attempt_count', '<', 3)  // Limit to less than 3 attempts
                ->limit($limit)
                ->get();

            if ($failedErrors->isEmpty()) {
                $this->info('No failed AI reviews found to retry.');
                return self::SUCCESS;
            }

            $retryCount = 0;
            $skipCount = 0;

            foreach ($failedErrors as $error) {
                try {
                    // Check if test result exists and is not already reviewed
                    $testResult = TestResult::find($error->test_result_id);

                    if (!$testResult) {
                        $skipCount++;
                        $this->line("  [SKIP] test_result_id: {$error->test_result_id} - test result not found");
                        continue;
                    }

                    if ($testResult->is_reviewed) {
                        $skipCount++;
                        $this->line("  [SKIP] test_result_id: {$error->test_result_id} - already reviewed");
                        continue;
                    }

                    // Skip if a COMPLETED AI review already exists — dispatching SendToAIServer
                    // would only trigger the idempotency early-return without generating a new review
                    $hasCompletedReview = AIReview::where('test_result_id', $error->test_result_id)
                        ->where('processing_status', 'COMPLETED')
                        ->exists();

                    if ($hasCompletedReview) {
                        $skipCount++;
                        $this->line("  [SKIP] test_result_id: {$error->test_result_id} - completed review already exists");
                        continue;
                    }

                    if (!$testResult->is_completed) {
                        $skipCount++;
                        $this->line("  [SKIP] test_result_id: {$error->test_result_id} - not completed");
                        continue;
                    }

                    // Increment attempt counter
                    $error->increment('attempt_count');

                    // Dispatch job to retry
                    SendToAIServer::dispatch($error->test_result_id);
                    $retryCount++;

                    $this->line("  [OK] Queued retry for test_result_id: {$error->test_result_id} (attempt {$error->attempt_count})");

                } catch (Exception $e) {
                    $skipCount++;
                    $this->error("  [ERROR] Failed to queue retry for test_result_id {$error->test_result_id}: {$e->getMessage()}");
                    Log::error('RetryFailedAIReviews: Failed to dispatch job', [
                        'test_result_id' => $error->test_result_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->info("\nSummary:");
            $this->info("  Retried: {$retryCount}");
            $this->info("  Skipped: {$skipCount}");

            Log::info('RetryFailedAIReviews: Command completed', [
                'hours' => $hours,
                'limit' => $limit,
                'retried' => $retryCount,
                'skipped' => $skipCount,
            ]);

            return self::SUCCESS;

        } catch (Exception $e) {
            $this->error("Command failed: {$e->getMessage()}");
            Log::error('RetryFailedAIReviews: Command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return self::FAILURE;
        }
    }
}
