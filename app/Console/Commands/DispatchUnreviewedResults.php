<?php

namespace App\Console\Commands;

use App\Jobs\ProcessAIReview;
use App\Models\TestResult;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DispatchUnreviewedResults extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:dispatch-unreviewed {--dry-run : Preview records without dispatching}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch unreviewed test results to AI review queue (batch of 10, runs hourly)';

    /**
     * Command timeout in seconds (2 minutes - fast dispatch)
     *
     * @var int
     */
    public $timeout = 120;

    /**
     * Cache lock key to prevent concurrent execution
     *
     * @var string
     */
    protected $lockKey = 'ai:dispatch-unreviewed';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Set resource limits
        ini_set('memory_limit', '128M');
        ini_set('max_execution_time', '120');

        // Acquire lock to prevent concurrent execution
        if (!$this->acquireLock()) {
            $this->warn('Another instance is already running. Exiting.');
            Log::channel('ai-command')->warning('Command skipped - another instance running');
            return Command::SUCCESS;
        }

        try {
            return $this->dispatchUnreviewedResults();
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * Main dispatching logic
     *
     * @return int
     */
    protected function dispatchUnreviewedResults()
    {
        $startTime = now();

        Log::channel('ai-command')->info('Dispatch command started', [
            'timestamp' => $startTime,
            'dry_run' => $this->option('dry-run')
        ]);

        // Fetch IDs with short-lived lock
        $testResultIds = $this->fetchUnreviewedIds();

        if (empty($testResultIds)) {
            $this->info('No unreviewed test results found.');
            Log::channel('ai-command')->info('No records to dispatch');
            return Command::SUCCESS;
        }

        // Dry-run mode
        if ($this->option('dry-run')) {
            return $this->handleDryRun($testResultIds);
        }

        // Dispatch jobs
        $dispatchedCount = 0;
        $failedDispatchCount = 0;

        $this->info("Dispatching " . count($testResultIds) . " AI review jobs to queue...");

        foreach ($testResultIds as $id) {
            try {
                ProcessAIReview::dispatch($id);
                $dispatchedCount++;

                Log::channel('ai-command')->info('Job dispatched', [
                    'test_result_id' => $id
                ]);
            } catch (Exception $e) {
                $failedDispatchCount++;

                Log::channel('ai-command')->error('Failed to dispatch job', [
                    'test_result_id' => $id,
                    'error' => $e->getMessage()
                ]);

                // Continue with next - don't abort batch
                continue;
            }
        }

        $processingTime = now()->diffInSeconds($startTime);

        // Summary output
        $this->info("\nDispatch complete:");
        $this->info("  Successfully dispatched: {$dispatchedCount}");
        if ($failedDispatchCount > 0) {
            $this->warn("  Failed to dispatch: {$failedDispatchCount}");
        }
        $this->info("  Time: {$processingTime} seconds");
        $this->info("\nJobs are processing in the background.");

        Log::channel('ai-command')->info('Dispatch command completed', [
            'total' => count($testResultIds),
            'dispatched' => $dispatchedCount,
            'failed_dispatch' => $failedDispatchCount,
            'processing_time_seconds' => $processingTime
        ]);

        // Determine exit code
        if ($failedDispatchCount > 0 && $dispatchedCount === 0) {
            return Command::FAILURE; // Complete dispatch failure
        }

        return Command::SUCCESS; // Success or partial success
    }

    /**
     * Fetch unreviewed test result IDs with database locking
     *
     * @return array
     */
    protected function fetchUnreviewedIds(): array
    {
        return DB::transaction(function () {
            return TestResult::where('is_completed', true)
                ->where('is_reviewed', false)
                ->whereYear('collected_date', date('Y'))
                ->orderBy('id', 'desc')
                ->limit(10)
                ->lockForUpdate()
                ->pluck('id')
                ->toArray();
        });
    }

    /**
     * Handle dry-run mode - preview records without dispatching
     *
     * @param array $testResultIds
     * @return int
     */
    protected function handleDryRun(array $testResultIds)
    {
        $this->info("DRY RUN MODE - No jobs will be dispatched\n");

        $testResults = TestResult::whereIn('id', $testResultIds)
            ->with('patient:id,icno')
            ->get();

        $this->table(
            ['ID', 'Patient ICNO', 'Ref ID', 'Updated At'],
            $testResults->map(fn($tr) => [
                $tr->id,
                $tr->patient->icno ?? 'N/A',
                $tr->ref_id ?? 'N/A',
                $tr->updated_at->format('Y-m-d H:i:s')
            ])
        );

        $this->info("\nWould dispatch {$testResults->count()} jobs to queue");

        Log::channel('ai-command')->info('Dry run completed', [
            'records_found' => $testResults->count()
        ]);

        return Command::SUCCESS;
    }

    /**
     * Acquire cache lock to prevent concurrent execution
     *
     * @return bool
     */
    protected function acquireLock(): bool
    {
        return Cache::lock($this->lockKey, 1800)->get();
    }

    /**
     * Release cache lock
     *
     * @return void
     */
    protected function releaseLock(): void
    {
        Cache::lock($this->lockKey)->release();
    }
}
