<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MigrationBatch;
use App\Models\MigrationBatchItem;
use Carbon\Carbon;

class MigrationDashboard extends Command
{
    protected $signature = 'migration:dashboard {--hours=24}';
    protected $description = 'Display migration status dashboard';

    public function handle()
    {
        $hours = $this->option('hours');
        $since = Carbon::now()->subHours($hours);

        $this->info("=== ODB Migration Dashboard (Last {$hours} hours) ===");
        $this->newLine();

        // Batch statistics
        $batches = MigrationBatch::where('created_at', '>=', $since)->get();

        $this->info("Batches:");
        $this->table(
            ['Status', 'Count', 'Avg Duration'],
            [
                ['Pending', $batches->where('status', 'pending')->count(), '-'],
                ['Processing', $batches->where('status', 'processing')->count(), '-'],
                ['Completed', $batches->where('status', 'completed')->count(),
                    $this->avgDuration($batches->where('status', 'completed'))],
                ['Partial Failure', $batches->where('status', 'partial_failure')->count(),
                    $this->avgDuration($batches->where('status', 'partial_failure'))]
            ]
        );

        $this->newLine();

        // Item statistics
        $totalReports = $batches->sum('total_reports');
        $successReports = $batches->sum('success');
        $failedReports = $batches->sum('failed');

        $this->info("Reports:");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Submitted', $totalReports],
                ['Successful', $successReports . ' (' . $this->percentage($successReports, $totalReports) . '%)'],
                ['Failed', $failedReports . ' (' . $this->percentage($failedReports, $totalReports) . '%)']]
        );

        $this->newLine();

        // Top errors
        $this->info("Top Errors:");
        $errors = MigrationBatchItem::where('status', 'failed')
            ->where('created_at', '>=', $since)
            ->selectRaw('error_message, COUNT(*) as count')
            ->groupBy('error_message')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        if ($errors->count() > 0) {
            foreach ($errors as $error) {
                $this->line("  [{$error->count}x] " . substr($error->error_message, 0, 80));
            }
        } else {
            $this->line("  No errors found");
        }

        $this->newLine();

        return 0;
    }

    protected function avgDuration($batches)
    {
        $durations = [];

        foreach ($batches as $batch) {
            if ($batch->started_at && $batch->completed_at) {
                $durations[] = $batch->started_at->diffInSeconds($batch->completed_at);
            }
        }

        if (empty($durations)) {
            return '-';
        }

        $avg = array_sum($durations) / count($durations);
        return gmdate('H:i:s', $avg);
    }

    protected function percentage($value, $total)
    {
        if ($total == 0) {
            return 0;
        }

        return round(($value / $total) * 100, 1);
    }
}
