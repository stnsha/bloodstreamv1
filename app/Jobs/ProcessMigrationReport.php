<?php

namespace App\Jobs;

use App\Models\MigrationBatch;
use App\Models\MigrationBatchItem;
use App\Services\ODB\MigrationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessMigrationReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $itemId;

    public $tries = 3;
    public $backoff = [60, 300, 900]; // 1 min, 5 min, 15 min

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
            ]);
        } catch (Throwable $e) {
            // Log error with full details
            Log::channel('migrate-log')->error('Failed to process migration report', [
                'item_id' => $this->itemId,
                'ref_id' => $item->ref_id,
                'batch_id' => $item->batch_id,
                'attempt' => $item->attempt_count,
                'max_tries' => $this->tries,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            // Check if we should retry
            if ($item->attempt_count < $this->tries) {
                $retryDelay = $this->backoff[$item->attempt_count - 1] ?? 900;

                // Log retry notification
                Log::channel('migrate-log')->warning('Migration report failed, will retry', [
                    'item_id' => $this->itemId,
                    'ref_id' => $item->ref_id,
                    'batch_id' => $item->batch_id,
                    'attempt' => $item->attempt_count,
                    'max_tries' => $this->tries,
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
}
