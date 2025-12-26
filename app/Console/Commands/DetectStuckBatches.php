<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MigrationBatch;
use App\Models\MigrationBatchItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DetectStuckBatches extends Command
{
    protected $signature = 'migration:detect-stuck {--fix}';
    protected $description = 'Detect and optionally fix stuck migration batches';

    public function handle()
    {
        $timeout = 30; // minutes
        $cutoff = Carbon::now()->subMinutes($timeout);

        // Find stuck batches
        $stuckBatches = MigrationBatch::where('status', 'processing')
            ->where('started_at', '<', $cutoff)
            ->get();

        if ($stuckBatches->count() == 0) {
            $this->info('No stuck batches found');
            return 0;
        }

        $this->warn("Found {$stuckBatches->count()} stuck batches:");

        foreach ($stuckBatches as $batch) {
            $duration = $batch->started_at->diffInMinutes(now());

            $this->line("  Batch {$batch->id} ({$batch->batch_uuid}): {$duration} minutes");

            if ($this->option('fix')) {
                $this->fixStuckBatch($batch);
            }
        }

        if (!$this->option('fix')) {
            $this->newLine();
            $this->info('Run with --fix to automatically resolve stuck batches');
        }

        return 0;
    }

    protected function fixStuckBatch($batch)
    {
        $this->line("    Fixing batch {$batch->id}...");

        // Mark pending/processing items as failed
        $updated = $batch->items()
            ->whereIn('status', ['pending', 'processing'])
            ->update([
                'status' => MigrationBatchItem::STATUS_FAILED,
                'error_message' => 'Batch timeout: exceeded 30 minutes',
                'processed_at' => now()
            ]);

        $this->line("    Marked {$updated} items as failed");

        // Update batch counters
        $success = $batch->items()->where('status', 'success')->count();
        $failed = $batch->items()->where('status', 'failed')->count();
        $processed = $success + $failed;

        $batch->update([
            'processed' => $processed,
            'success' => $success,
            'failed' => $failed,
            'status' => $failed > 0 ? 'partial_failure' : 'completed',
            'completed_at' => now()
        ]);

        $this->line("    Batch marked as {$batch->status}");

        Log::channel('migrate-log')->warning('Stuck batch auto-fixed', [
            'batch_id' => $batch->id,
            'batch_uuid' => $batch->batch_uuid,
            'duration_minutes' => $batch->started_at->diffInMinutes(now())
        ]);
    }
}
