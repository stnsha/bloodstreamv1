<?php

namespace App\Jobs;

use App\Models\TestResult;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ProcessTestResultsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1800; // 30 minutes
    public $maxExceptions = 3;
    public $backoff = [60, 300, 900]; // 1min, 5min, 15min

    private $batchSize;
    private $maxResults;

    /**
     * Create a new job instance.
     */
    public function __construct(int $batchSize = 15, int $maxResults = 200)
    {
        $this->batchSize = $batchSize;
        $this->maxResults = $maxResults;
    }

    /**
     * Get the middleware the job should pass through.
     */
    public function middleware(): array
    {
        return [
            new WithoutOverlapping('process_test_results', 3600), // Prevent overlap for 1 hour
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $startTime = now();
        Log::info('ProcessTestResultsJob started', [
            'batch_size' => $this->batchSize,
            'max_results' => $this->maxResults,
            'start_time' => $startTime
        ]);

        try {
            // Get test results that need processing
            $testResults = TestResult::with([
                'patient',
                'testResultItems.panelPanelItem.panel.panelCategory',
                'testResultItems.referenceRange',
                'testResultItems.panelPanelItem.panelItem',
                'testResultItems.panelComments.masterPanelComment',
            ])
                ->where('is_reviewed', false)
                ->where('is_completed', true)
                ->whereHas('patient', function ($query) {
                    $query->where('ic_type', 'NRIC');
                })
                ->take($this->maxResults)
                ->get();

            if ($testResults->isEmpty()) {
                Log::info('No test results found to process');
                return;
            }

            $totalResults = $testResults->count();
            $totalBatches = ceil($totalResults / $this->batchSize);

            Log::info('Test results processing plan', [
                'total_results' => $totalResults,
                'batch_size' => $this->batchSize,
                'total_batches' => $totalBatches
            ]);

            // Split into batches and dispatch batch jobs
            $testResults->chunk($this->batchSize)->each(function ($batch, $batchIndex) use ($totalBatches) {
                $batchNumber = $batchIndex + 1;
                
                Log::info("Dispatching batch {$batchNumber}/{$totalBatches}", [
                    'batch_number' => $batchNumber,
                    'batch_size' => $batch->count(),
                    'test_result_ids' => $batch->pluck('id')->toArray()
                ]);

                // Dispatch each batch as a separate job with delay to manage rate limiting
                ProcessTestResultBatchJob::dispatch($batch->pluck('id')->toArray(), $batchNumber)
                    ->delay(now()->addSeconds($batchIndex * 2)); // 2 seconds delay between batches
            });

            $endTime = now();
            $duration = $endTime->diffInSeconds($startTime);

            Log::info('ProcessTestResultsJob completed successfully', [
                'total_results' => $totalResults,
                'total_batches' => $totalBatches,
                'duration_seconds' => $duration,
                'end_time' => $endTime
            ]);

        } catch (\Exception $e) {
            Log::error('ProcessTestResultsJob failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'duration_seconds' => now()->diffInSeconds($startTime)
            ]);
            
            throw $e; // Re-throw to trigger job failure handling
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessTestResultsJob failed permanently', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'attempts' => $this->attempts()
        ]);
    }
}