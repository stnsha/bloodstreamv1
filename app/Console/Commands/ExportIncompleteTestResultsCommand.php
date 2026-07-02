<?php

namespace App\Console\Commands;

use App\Exports\IncompleteTestResultsExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ExportIncompleteTestResultsCommand extends Command
{
    protected $signature = 'export:incomplete-test-results';

    protected $description = 'Export incomplete_test_results (ref_id, lab_no) to a timestamped CSV file in storage/app/public/csv';

    public function handle(): int
    {
        Log::channel('ai-command')->info('ExportIncompleteTestResultsCommand: started');

        try {
            Storage::disk('public')->makeDirectory('csv');

            $filename = 'incomplete_test_results_' . now()->format('Y-m-d_His') . '.csv';
            $storagePath = 'public/csv/' . $filename;

            Excel::store(new IncompleteTestResultsExport(), $storagePath, null, ExcelFormat::CSV);

            $fullPath = storage_path('app/' . $storagePath);

            $this->info("Incomplete test results exported to: {$fullPath}");

            Log::channel('ai-command')->info('ExportIncompleteTestResultsCommand: completed', [
                'filename' => $filename,
                'full_path' => $fullPath,
            ]);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Export failed: {$e->getMessage()}");

            Log::channel('ai-command')->error('ExportIncompleteTestResultsCommand: failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }
}
