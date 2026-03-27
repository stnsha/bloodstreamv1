<?php

namespace App\Console\Commands;

use App\Exports\IncompleteLabNumbersExport;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ExportIncompleteLabNumbers extends Command
{
    protected $signature = 'export:incomplete-lab-numbers';

    protected $description = 'Export incomplete and unreviewed lab numbers to Excel';

    public function handle(): int
    {
        ini_set('max_execution_time', '0');

        Log::info('Starting incomplete lab numbers export');

        try {
            Storage::makeDirectory('public/excel');

            $filename = 'incomplete_lab_numbers_' . now()->format('Y-m-d_His') . '.xlsx';

            Excel::queue(new IncompleteLabNumbersExport(), 'public/excel/' . $filename);

            Log::info('Incomplete lab numbers export queued', ['file' => $filename]);

            $this->info('Export queued: ' . $filename);

            return self::SUCCESS;
        } catch (Exception $e) {
            Log::error('Incomplete lab numbers export failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->error('Export failed: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
