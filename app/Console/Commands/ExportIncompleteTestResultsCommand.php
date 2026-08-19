<?php

namespace App\Console\Commands;

use App\Exports\IncompleteTestResultsExport;
use App\Services\PanelCompletenessService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ExportIncompleteTestResultsCommand extends Command
{
    protected $signature = 'export:incomplete-test-results
                            {--from= : Start of test_results.created_at range (Y-m-d), defaults to 30 days ago}
                            {--to=   : End of test_results.created_at range (Y-m-d), defaults to today}';

    protected $description = 'Export test_results with is_completed=0 and is_reviewed=0 (id, doctor_id, patient_id, ref_id, lab_no, dates, is_completed, is_reviewed, manually_completed_at, timestamps, reason, missing_details) to a timestamped CSV file in storage/app/public/csv';

    public function handle(PanelCompletenessService $panelCompletenessService): int
    {
        $from = Carbon::parse($this->option('from') ?? now()->subDays(30)->toDateString())->startOfDay();
        $to = Carbon::parse($this->option('to') ?? now()->toDateString())->endOfDay();

        Log::channel('ai-command')->info('ExportIncompleteTestResultsCommand: started', [
            'from' => $from->toDateTimeString(),
            'to' => $to->toDateTimeString(),
        ]);

        try {
            Storage::disk('public')->makeDirectory('csv');

            $filename = 'incomplete_test_results_' . now()->format('Y-m-d_His') . '.csv';
            $storagePath = 'public/csv/' . $filename;

            Excel::store(new IncompleteTestResultsExport($panelCompletenessService, $from, $to), $storagePath, null, ExcelFormat::CSV);

            $fullPath = storage_path('app/' . $storagePath);

            $this->info("Incomplete test results exported to: {$fullPath}");

            Log::channel('ai-command')->info('ExportIncompleteTestResultsCommand: completed', [
                'filename' => $filename,
                'full_path' => $fullPath,
                'from' => $from->toDateTimeString(),
                'to' => $to->toDateTimeString(),
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
