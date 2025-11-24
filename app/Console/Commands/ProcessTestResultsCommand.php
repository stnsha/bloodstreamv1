<?php

namespace App\Console\Commands;

use App\Jobs\ProcessAIReviewJob;
use App\Models\TestResult;
use App\Services\ApiTokenService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

class ProcessTestResultsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'bloodstream:process-results 
                            {--batch-size=15 : Number of test results per batch}
                            {--max-results=200 : Maximum number of test results to process}
                            {--force-token-refresh : Force refresh API token}
                            {--clear-cache : Clear all related caches}
                            {--dry-run : Show what would be processed without actually processing}';

    /**
     * The console command description.
     */
    protected $description = 'Process test results for AI analysis in batches';

    /**
     * Execute the console command.
     */
    public function handle(ApiTokenService $apiTokenService): int
    {
        $this->info('Blood Stream Test Results Processor');
        $this->info('=====================================');

        $batchSize = (int) $this->option('batch-size');
        $maxResults = (int) $this->option('max-results');
        $forceTokenRefresh = $this->option('force-token-refresh');
        $clearCache = $this->option('clear-cache');
        $dryRun = $this->option('dry-run');

        // Validate options
        if ($batchSize < 1 || $batchSize > 50) {
            $this->error('Batch size must be between 1 and 50');
            return self::FAILURE;
        }

        if ($maxResults < 1 || $maxResults > 500) {
            $this->error('Max results must be between 1 and 500');
            return self::FAILURE;
        }

        $this->info("Configuration:");
        $this->info("- Batch Size: {$batchSize}");
        $this->info("- Max Results: {$maxResults}");
        $this->info("- Dry Run: " . ($dryRun ? 'Yes' : 'No'));

        // Clear cache if requested
        if ($clearCache) {
            $this->info("\nClearing caches...");
            Cache::flush();
            $this->info("✓ All caches cleared");
        }

        // Handle token refresh
        if ($forceTokenRefresh) {
            $this->info("\nForcing API token refresh...");
            $apiTokenService->clearToken();
            $token = $apiTokenService->refreshToken();
            if ($token) {
                $this->info("✓ API token refreshed successfully");
            } else {
                $this->error("✗ Failed to refresh API token");
                return self::FAILURE;
            }
        } else {
            // Check if we have a valid token
            $this->info("\nChecking API token...");
            $token = $apiTokenService->getValidToken();
            if ($token) {
                $this->info("✓ Valid API token found");
            } else {
                $this->error("✗ No valid API token available");
                $this->info("Use --force-token-refresh to get a new token");
                return self::FAILURE;
            }
        }

        // Check queue connection
        $this->info("\nChecking queue connection...");
        try {
            $queueSize = Queue::size();
            $this->info("✓ Queue connection successful (current size: {$queueSize})");
        } catch (Exception $e) {
            $this->error("✗ Queue connection failed: " . $e->getMessage());
            $this->info("Make sure Redis is running and queue is properly configured");
            return self::FAILURE;
        }

        if ($dryRun) {
            $this->info("\n🔍 DRY RUN MODE - No actual processing will occur");

            // Count test results that would be processed
            $testResultsQuery = TestResult::where('is_reviewed', false)
                ->where('is_completed', true)
                ->whereHas('patient', function ($query) {
                    $query->where('ic_type', 'NRIC');
                });

            $totalAvailable = $testResultsQuery->count();
            $willProcess = min($totalAvailable, $maxResults);
            $batches = ceil($willProcess / $batchSize);

            $this->info("\nTest Results Summary:");
            $this->info("- Total available: {$totalAvailable}");
            $this->info("- Will process: {$willProcess}");
            $this->info("- Number of batches: {$batches}");
            $this->info("- Results per batch: {$batchSize}");

            if ($totalAvailable === 0) {
                $this->warn("No test results found to process");
                return self::SUCCESS;
            }

            $this->info("\nBatch breakdown:");
            for ($i = 1; $i <= $batches; $i++) {
                $start = ($i - 1) * $batchSize + 1;
                $end = min($i * $batchSize, $willProcess);
                $this->info("- Batch {$i}: Results {$start}-{$end}");
            }

            $this->info("\n✓ Dry run completed successfully");
            return self::SUCCESS;
        }

        // Dispatch the main job
        $this->info("\nDispatching ProcessAIReviewJob...");

        try {
            ProcessAIReviewJob::dispatch($batchSize, $maxResults);
            $this->info("✓ Job dispatched successfully");
            // $this->info("\nThe job will:");
            // $this->info("1. Fetch up to {$maxResults} unreviewed test results");
            // $this->info("2. Split them into batches of {$batchSize}");
            // $this->info("3. Process each batch with rate limiting (5 API calls/second)");
            // $this->info("4. Cache patient data for 1 hour");
            // $this->info("5. Use cached API token (valid for 30 days)");
            // $this->info("6. Log all progress and failures");

            // $this->info("\nMonitor progress with:");
            // $this->info("- tail -f storage/logs/laravel.log");

        } catch (Exception $e) {
            $this->error("✗ Failed to dispatch job: " . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}