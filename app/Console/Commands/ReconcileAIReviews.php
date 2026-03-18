<?php

namespace App\Console\Commands;

use App\Jobs\SendToAIServer;
use App\Models\AIReview;
use App\Models\TestResult;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcileAIReviews extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:reconcile-reviews {--dry-run : Show what would be done without actually doing it} {--hours=24 : Only reconcile test results from the last N hours} {--limit=500 : Maximum number of records to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find orphaned test results (PDF received but not reviewed) and dispatch AI review jobs';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $hours = $this->option('hours');
        $limit = $this->option('limit');

        $action = $dryRun ? 'Would dispatch' : 'Dispatching';
        $this->info("{$action} AI review jobs for orphaned test results from the last {$hours} hours...");

        try {
            // Find test results that are completed but have no COMPLETED ai_review.
            // Non-COMPLETED records (PENDING/QUEUED/FAILED) are treated as absent
            // because they are cleaned up by the job on failure.
            $orphanedResults = TestResult::where('is_completed', true)
                ->where('is_reviewed', false)
                ->where('created_at', '>=', now()->subHours($hours))
                ->whereDoesntHave('aiReview', function ($query) {
                    $query->where('processing_status', 'COMPLETED');
                })
                ->limit($limit)
                ->get();

            if ($orphanedResults->isEmpty()) {
                $this->info('No orphaned test results found.');
                return self::SUCCESS;
            }

            $dispatchedCount = 0;
            $skippedCount = 0;
            $errorCount = 0;

            foreach ($orphanedResults as $result) {
                try {
                    if ($dryRun) {
                        $this->line("  [DRY-RUN] Would dispatch AI review for test_result_id: {$result->id} (ref_id: {$result->ref_id})");
                        $dispatchedCount++;
                        continue;
                    }

                    // Dispatch job to process AI review
                    SendToAIServer::dispatch($result->id);
                    $dispatchedCount++;

                    $this->line("  [OK] Dispatched AI review for test_result_id: {$result->id} (ref_id: {$result->ref_id})");

                } catch (\Exception $e) {
                    $errorCount++;
                    $this->error("  [ERROR] Failed to dispatch for test_result_id {$result->id}: {$e->getMessage()}");
                    Log::error('ReconcileAIReviews: Failed to dispatch job', [
                        'test_result_id' => $result->id,
                        'ref_id' => $result->ref_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->info("\nSummary:");
            $this->info("  Processed: " . ($dispatchedCount + $errorCount));
            if ($dryRun) {
                $this->info("  Would dispatch: {$dispatchedCount}");
            } else {
                $this->info("  Dispatched: {$dispatchedCount}");
            }
            $this->info("  Errors: {$errorCount}");

            Log::info('ReconcileAIReviews: Command completed', [
                'dry_run' => $dryRun,
                'hours' => $hours,
                'limit' => $limit,
                'dispatched' => $dispatchedCount,
                'errors' => $errorCount,
            ]);

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Command failed: {$e->getMessage()}");
            Log::error('ReconcileAIReviews: Command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return self::FAILURE;
        }
    }
}
