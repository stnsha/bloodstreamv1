<?php

namespace App\Jobs;

use App\Models\MigrationBatch;
use App\Models\MigrationBatchItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessMigrationBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $batchId;

    /**
     * Create a new job instance.
     */
    public function __construct($batchId)
    {
        $this->batchId = $batchId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $batch = MigrationBatch::find($this->batchId);

            if (!$batch) {
                Log::channel('migrate-log')->error('Migration batch not found', ['batch_id' => $this->batchId]);
                return;
            }

            // Update batch status to processing
            $batch->update([
                'status' => MigrationBatch::STATUS_PROCESSING,
                'started_at' => now(),
            ]);

            // Get all pending items
            $items = $batch->items()->where('status', MigrationBatchItem::STATUS_PENDING)->get();

            // Dispatch individual report processing jobs
            foreach ($items as $item) {
                ProcessMigrationReport::dispatch($item->id);
            }

            Log::channel('migrate-log')->info('Migration batch processing started', [
                'batch_id' => $this->batchId,
                'total_items' => $items->count()
            ]);
        } catch (Throwable $e) {
            Log::channel('migrate-log')->error('Failed to process migration batch', [
                'batch_id' => $this->batchId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
