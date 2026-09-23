<?php

namespace App\Console\Commands;

use App\Jobs\SendToAIServer;
use App\Models\AIReview;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class RetryFailedAIReviews extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:retry-failed-reviews {--hours=24 : Only retry reviews superseded within the last N hours} {--limit=100 : Maximum number of reviews to retry}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retry test results whose ai_reviews row is SUPERSEDED (a previous send/webhook failed) - no attempt cap, keeps retrying until it completes.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $limit = (int) $this->option('limit');

        $this->info("Retrying SUPERSEDED AI reviews from the last {$hours} hours (max {$limit})...");

        try {
            // Source of truth is ai_reviews.processing_status, not ai_errors - one row
            // per test_result_id, so this naturally dedupes (an ai_errors row exists per
            // failure and can have many rows for the same test result over time).
            // is_completed/is_reviewed are enforced here too, same as
            // DispatchUnreviewedResultsAsync, so a record that became reviewed or
            // reverted to incomplete since the failure is skipped without a per-row check.
            $staleReviews = AIReview::where('processing_status', 'SUPERSEDED')
                ->where('updated_at', '>=', now()->subHours($hours))
                ->whereHas('testResult', function ($query) {
                    $query->where('is_completed', true)
                        ->where('is_reviewed', false);
                })
                ->orderBy('updated_at')
                ->limit($limit)
                ->get();

            if ($staleReviews->isEmpty()) {
                $this->info('No SUPERSEDED AI reviews found to retry.');

                return self::SUCCESS;
            }

            $retryCount = 0;
            $failCount = 0;

            foreach ($staleReviews as $review) {
                try {
                    SendToAIServer::dispatch($review->test_result_id);
                    $retryCount++;

                    $this->line("  [OK] Queued retry for test_result_id: {$review->test_result_id}");
                } catch (Throwable $e) {
                    $failCount++;
                    $this->error("  [ERROR] Failed to queue retry for test_result_id {$review->test_result_id}: {$e->getMessage()}");
                    Log::channel('ai-command')->error('RetryFailedAIReviews: failed to dispatch job', [
                        'test_result_id' => $review->test_result_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->info("\nSummary:");
            $this->info("  Retried: {$retryCount}");
            if ($failCount > 0) {
                $this->warn("  Failed to queue: {$failCount}");
            }

            Log::channel('ai-command')->info('RetryFailedAIReviews: command completed', [
                'hours' => $hours,
                'limit' => $limit,
                'retried' => $retryCount,
                'failed_to_queue' => $failCount,
            ]);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Command failed: {$e->getMessage()}");
            Log::channel('ai-command')->error('RetryFailedAIReviews: command failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ]);

            return self::FAILURE;
        }
    }
}
