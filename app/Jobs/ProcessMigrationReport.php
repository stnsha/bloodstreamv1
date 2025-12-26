<?php

namespace App\Jobs;

use App\Models\MigrationBatch;
use App\Models\MigrationBatchItem;
use App\Services\ODB\MigrationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessMigrationReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $itemId;

    public $tries = 3;
    public $backoff = [60, 300, 900]; // 1 min, 5 min, 15 min

    /**
     * The number of seconds the job can run before timing out
     *
     * @var int
     */
    public $timeout = 120;  // 2 minutes hard limit

    /**
     * Get the middleware the job should pass through
     *
     * @return array
     */
    public function middleware()
    {
        return [
            // Prevent concurrent processing of same item
            new WithoutOverlapping($this->itemId),

            // Rate limit: max 10 jobs per minute per partition (Laravel 10 syntax)
            new RateLimited('migration-processing'),

            // Throttle exceptions: max 3 exceptions per 5 minutes
            new ThrottlesExceptions(3, 5)
        ];
    }

    /**
     * Create a new job instance.
     */
    public function __construct($itemId)
    {
        $this->itemId = $itemId;
    }

    /**
     * Execute the job.
     */
    public function handle(MigrationService $migrationService): void
    {
        $item = MigrationBatchItem::find($this->itemId);

        if (!$item) {
            Log::channel('migrate-log')->error('Migration batch item not found', ['item_id' => $this->itemId]);
            return;
        }

        // Check if batch has timed out
        $this->checkBatchTimeout($item->batch_id);

        // Track memory usage
        $memoryBefore = memory_get_usage(true) / 1024 / 1024;

        Log::channel('migrate-log')->debug('Job started', [
            'item_id' => $this->itemId,
            'ref_id' => $item->ref_id,
            'memory_mb' => round($memoryBefore, 2)
        ]);

        try {
            // Update status to processing
            $item->update([
                'status' => MigrationBatchItem::STATUS_PROCESSING,
                'attempt_count' => $item->attempt_count + 1,
            ]);

            // Get the report data from the item (stored in batch when created)
            $reportData = json_decode($item->report_data, true);

            // Log job start with report details
            Log::channel('migrate-log')->info('Starting migration report processing', [
                'item_id' => $this->itemId,
                'ref_id' => $item->ref_id,
                'batch_id' => $item->batch_id,
                'attempt' => $item->attempt_count,
                'patient_name' => $reportData['report']['patient_name'] ?? 'N/A',
                'test_date' => $reportData['report']['test_date'] ?? 'N/A',
                'parameter_count' => count($reportData['parameter'] ?? []),
            ]);

            // Process the report using MigrationService
            $testResult = $migrationService->processReport($reportData['report'], $reportData['parameter']);

            // Mark as success
            $item->update([
                'status' => MigrationBatchItem::STATUS_SUCCESS,
                'test_result_id' => $testResult->id,
                'processed_at' => now(),
            ]);

            // Update batch counters
            $this->updateBatchCounters($item->batch_id);

            // Log memory usage after processing
            $memoryAfter = memory_get_usage(true) / 1024 / 1024;

            // Log successful completion with detailed summary
            Log::channel('migrate-log')->info('Migration report processed successfully', [
                'item_id' => $this->itemId,
                'ref_id' => $item->ref_id,
                'batch_id' => $item->batch_id,
                'test_result_id' => $testResult->id,
                'lab_no' => $testResult->lab_no,
                'patient_name' => $reportData['report']['patient_name'] ?? 'N/A',
                'test_date' => $reportData['report']['test_date'] ?? 'N/A',
                'parameter_count' => count($reportData['parameter'] ?? []),
                'memory_before_mb' => round($memoryBefore, 2),
                'memory_after_mb' => round($memoryAfter, 2),
                'memory_delta_mb' => round($memoryAfter - $memoryBefore, 2)
            ]);
        } catch (Throwable $e) {
            $errorMessage = $e->getMessage();
            $isDeadlock = false;

            // Detect MySQL deadlock errors
            if (strpos($errorMessage, 'Deadlock found') !== false ||
                strpos($errorMessage, 'Lock wait timeout exceeded') !== false) {

                $isDeadlock = true;

                Log::channel('migrate-log')->error('Database deadlock detected', [
                    'item_id' => $this->itemId,
                    'ref_id' => $item->ref_id,
                    'batch_id' => $item->batch_id,
                    'error' => $errorMessage
                ]);
            } else {
                // Log error with full details
                Log::channel('migrate-log')->error('Failed to process migration report', [
                    'item_id' => $this->itemId,
                    'ref_id' => $item->ref_id,
                    'batch_id' => $item->batch_id,
                    'attempt' => $item->attempt_count,
                    'max_tries' => $this->tries,
                    'error' => $errorMessage,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }

            // Check if we should retry
            if ($item->attempt_count < $this->tries) {
                $baseDelay = $this->backoff[$item->attempt_count - 1] ?? 900;

                // Add jitter for deadlocks to prevent retry storms
                if ($isDeadlock) {
                    $jitter = rand(1, 10);  // Random 1-10 second jitter
                    $retryDelay = $baseDelay + $jitter;
                } else {
                    $retryDelay = $baseDelay;
                }

                // Log retry notification
                Log::channel('migrate-log')->warning('Migration report failed, will retry', [
                    'item_id' => $this->itemId,
                    'ref_id' => $item->ref_id,
                    'batch_id' => $item->batch_id,
                    'attempt' => $item->attempt_count,
                    'max_tries' => $this->tries,
                    'is_deadlock' => $isDeadlock,
                    'retry_delay_seconds' => $retryDelay,
                    'error' => $e->getMessage(),
                ]);

                // Release back to queue with delay
                $this->release($retryDelay);
            } else {
                // Mark as failed after max attempts
                $item->update([
                    'status' => MigrationBatchItem::STATUS_FAILED,
                    'error_message' => $e->getMessage(),
                    'processed_at' => now(),
                ]);

                // Log final failure after all retries exhausted
                Log::channel('migrate-log')->error('Migration report permanently failed after all retries', [
                    'item_id' => $this->itemId,
                    'ref_id' => $item->ref_id,
                    'batch_id' => $item->batch_id,
                    'total_attempts' => $item->attempt_count,
                    'final_error' => $e->getMessage(),
                ]);

                // Update batch counters
                $this->updateBatchCounters($item->batch_id);
            }
        }
    }

    protected function updateBatchCounters($batchId)
    {
        $batch = MigrationBatch::find($batchId);

        if (!$batch) {
            return;
        }

        $success = $batch->items()->where('status', MigrationBatchItem::STATUS_SUCCESS)->count();
        $failed = $batch->items()->where('status', MigrationBatchItem::STATUS_FAILED)->count();
        $processed = $success + $failed;

        $batch->update([
            'processed' => $processed,
            'success' => $success,
            'failed' => $failed,
        ]);

        // Check if all items are processed
        if ($processed >= $batch->total_reports) {
            $status = $failed > 0 ?
                MigrationBatch::STATUS_PARTIAL_FAILURE :
                MigrationBatch::STATUS_COMPLETED;

            $batch->update([
                'status' => $status,
                'completed_at' => now(),
            ]);

            // Log batch completion with detailed summary
            Log::channel('migrate-log')->info('Migration batch completed', [
                'batch_id' => $batchId,
                'total_reports' => $batch->total_reports,
                'successful' => $success,
                'failed' => $failed,
                'success_rate' => $batch->total_reports > 0
                    ? round(($success / $batch->total_reports) * 100, 2) . '%'
                    : '0%',
                'final_status' => $status,
                'completed_at' => now()->toDateTimeString(),
            ]);
        }
    }

    /**
     * Check if batch has timed out and mark stuck items as failed
     *
     * @param int $batchId
     * @return void
     */
    protected function checkBatchTimeout($batchId)
    {
        $batch = MigrationBatch::find($batchId);

        if (!$batch) {
            return;
        }

        // If batch has been processing for more than 30 minutes, mark as failed
        if ($batch->status === MigrationBatch::STATUS_PROCESSING &&
            $batch->started_at &&
            $batch->started_at->diffInMinutes(now()) > 30) {

            Log::channel('migrate-log')->error('Batch timeout detected', [
                'batch_id' => $batchId,
                'batch_uuid' => $batch->batch_uuid,
                'started_at' => $batch->started_at,
                'duration_minutes' => $batch->started_at->diffInMinutes(now())
            ]);

            // Mark remaining pending/processing items as failed
            $batch->items()
                ->whereIn('status', [MigrationBatchItem::STATUS_PENDING, MigrationBatchItem::STATUS_PROCESSING])
                ->update([
                    'status' => MigrationBatchItem::STATUS_FAILED,
                    'error_message' => 'Batch timeout: exceeded 30 minutes',
                    'processed_at' => now()
                ]);

            // Update batch counters
            $this->updateBatchCounters($batchId);
        }
    }
}
