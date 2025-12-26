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

            Log::channel('migrate-log')->info('ProcessMigrationBatch: Dispatching jobs', [
                'batch_id' => $this->batchId,
                'total_items' => $items->count()
            ]);

            // Dispatch with throttling to prevent queue flooding
            foreach ($items as $index => $item) {
                // Stagger dispatch: 100ms delay per job
                $delay = ($index * 0.1);  // 0.1 second = 100ms

                ProcessMigrationReport::dispatch($item->id)
                    ->onQueue('default')  // Use default queue
                    ->delay(now()->addSeconds($delay));

                // Log every 10th dispatch
                if ($index % 10 == 0 || $index == $items->count() - 1) {
                    Log::channel('migrate-log')->debug('Dispatched jobs', [
                        'batch_id' => $this->batchId,
                        'dispatched' => $index + 1,
                        'total' => $items->count()
                    ]);
                }
            }

            Log::channel('migrate-log')->info('ProcessMigrationBatch: All jobs dispatched', [
                'batch_id' => $this->batchId,
                'dispatched_count' => $items->count()
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
