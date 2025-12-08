<?php

namespace App\Console\Commands;

use App\Models\TestResult;
use App\Services\AIReviewService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessUnreviewedResults extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:process-unreviewed {--dry-run : Preview records without processing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process unreviewed test results through AI review service (batch of 10, runs hourly)';

    /**
     * Command timeout in seconds (1 hour)
     *
     * @var int
     */
    public $timeout = 3600;

    /**
     * Cache lock key to prevent concurrent execution
     *
     * @var string
     */
    protected $lockKey = 'ai:process-unreviewed';

    /**
     * AI Review Service instance
     *
     * @var AIReviewService
     */
    protected $aiReviewService;

    /**
     * Create a new command instance.
     *
     * @param AIReviewService $aiReviewService
     */
    public function __construct(AIReviewService $aiReviewService)
    {
        parent::__construct();
        $this->aiReviewService = $aiReviewService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Set resource limits to prevent CPU clogging
        ini_set('memory_limit', '256M');
        ini_set('max_execution_time', '3600');

        // Acquire lock to prevent concurrent execution
        if (!$this->acquireLock()) {
            $this->warn('Another instance is already running. Exiting.');
            Log::channel('ai-command')->warning('Command skipped - another instance running');
            return Command::SUCCESS;
        }

        try {
            return $this->processUnreviewedResults();
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * Main processing logic
     *
     * @return int
     */
    protected function processUnreviewedResults()
    {
        $startTime = now();

        Log::channel('ai-command')->info('Command started', [
            'timestamp' => $startTime,
            'dry_run' => $this->option('dry-run')
        ]);

        // Fetch IDs with short-lived lock
        $testResultIds = $this->fetchUnreviewedIds();

        if (empty($testResultIds)) {
            $this->info('No unreviewed test results found.');
            Log::channel('ai-command')->info('No records to process');
            return Command::SUCCESS;
        }

        // Dry-run mode
        if ($this->option('dry-run')) {
            return $this->handleDryRun($testResultIds);
        }

        // Process records
        $successCount = 0;
        $failureCount = 0;

        $this->info("Processing " . count($testResultIds) . " unreviewed test results...");
        $this->output->progressStart(count($testResultIds));

        foreach ($testResultIds as $id) {
            try {
                $result = $this->aiReviewService->processSingle($id);

                if ($result->isSuccessful()) {
                    $successCount++;
                    Log::channel('ai-command')->info('Processed successfully', [
                        'test_result_id' => $id
                    ]);
                } else {
                    $failureCount++;
                    Log::channel('ai-command')->error('Processing failed', [
                        'test_result_id' => $id,
                        'error' => $result->errorMessage
                    ]);
                }

                $this->output->progressAdvance();

                // Small delay to prevent API rate limiting and CPU saturation
                sleep(2);

                // Garbage collection every 5 iterations
                if (($successCount + $failureCount) % 5 === 0) {
                    gc_collect_cycles();
                }
            } catch (Exception $e) {
                $failureCount++;
                Log::channel('ai-command')->error('Exception during processing', [
                    'test_result_id' => $id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                $this->output->progressAdvance();

                // Continue with next record - don't abort batch
                continue;
            }
        }

        $this->output->progressFinish();

        // Summary
        $processingTime = now()->diffInSeconds($startTime);

        $this->info("\nProcessing complete:");
        $this->info("  Successful: {$successCount}");
        $this->info("  Failed: {$failureCount}");
        $this->info("  Time: {$processingTime} seconds");

        Log::channel('ai-command')->info('Command completed', [
            'total' => count($testResultIds),
            'successful' => $successCount,
            'failed' => $failureCount,
            'processing_time_seconds' => $processingTime
        ]);

        // Determine exit code
        if ($failureCount > 0 && $successCount === 0) {
            return Command::FAILURE; // Complete failure
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
     * Handle dry-run mode - preview records without processing
     *
     * @param array $testResultIds
     * @return int
     */
    protected function handleDryRun(array $testResultIds)
    {
        $this->info("DRY RUN MODE - No records will be processed\n");

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

        $this->info("\nWould process {$testResults->count()} records");

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