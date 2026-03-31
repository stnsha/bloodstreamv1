<?php

namespace App\Console\Commands;

use App\Exports\ThyroidDataExport;
use App\Services\ThyroidExportService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ExportThyroidDataCommand extends Command
{
    protected $signature = 'export:thyroid
                            {--from=2025-01-01 : Start collected_date inclusive (Y-m-d)}
                            {--to=2026-03-30   : End collected_date inclusive (Y-m-d)}
                            {--limit=          : Limit output to N rows (useful for sample runs)}
                            {--dry-run         : Count matching rows without generating the file}';

    protected $description = 'Export thyroid panel results (TSH, FT4, FT3) for lab_id=2 to CSV';

    public function handle(ThyroidExportService $service): int
    {
        $dateFrom = $this->option('from');
        $dateTo   = $this->option('to');
        $dryRun   = (bool) $this->option('dry-run');
        $limit    = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        Log::channel('thyroid-export')->info('ExportThyroidDataCommand: started', [
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
            'dry_run'   => $dryRun,
            'limit'     => $limit,
        ]);

        $limitLabel = $limit !== null ? " | limit: {$limit}" : '';
        $this->info("Thyroid export | from: {$dateFrom} | to: {$dateTo}{$limitLabel}" . ($dryRun ? ' | DRY-RUN' : ''));

        try {
            if ($dryRun) {
                $count = $service->countMatchingRows($dateFrom, $dateTo);
                $this->info("Dry-run: {$count} rows would be exported.");

                Log::channel('thyroid-export')->info('ExportThyroidDataCommand: dry-run count', [
                    'count' => $count,
                ]);

                return self::SUCCESS;
            }

            Storage::makeDirectory('public/csv');

            $filename    = 'thyroid_' . now()->format('Y-m-d_His') . '.csv';
            $storagePath = 'public/csv/' . $filename;

            Excel::store(
                new ThyroidDataExport($service, $dateFrom, $dateTo, $limit),
                $storagePath,
                null,
                \Maatwebsite\Excel\Excel::CSV
            );

            $fullPath = storage_path('app/' . $storagePath);

            $this->info("Export complete: {$fullPath}");

            Log::channel('thyroid-export')->info('ExportThyroidDataCommand: completed', [
                'filename'  => $filename,
                'full_path' => $fullPath,
            ]);

            return self::SUCCESS;

        } catch (Exception $e) {
            $this->error("Export failed: {$e->getMessage()}");

            Log::channel('thyroid-export')->error('ExportThyroidDataCommand: failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }
}
