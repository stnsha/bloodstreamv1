<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Throwable;

class ConsultCallDailyEligibilitySweep extends Command
{
    protected $signature = 'consult-call:daily-eligibility-sweep';

    protected $description = 'Re-check consult call eligibility for completed test results collected from the 1st of the current month through today, in batches of 50';

    private const BATCH_SIZE = 50;

    private const MAX_PAGES = 200; // safety cap: 200 * 50 = 10,000 records/day

    public function handle(): int
    {
        $dateFrom = Carbon::now()->startOfMonth()->toDateString();
        $dateTo = Carbon::now()->toDateString();

        Log::info('ConsultCallDailyEligibilitySweep: Starting', [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'batch_size' => self::BATCH_SIZE,
        ]);

        try {
            $offset = 0;
            $page = 0;
            $noRecordsFound = false;

            do {
                Artisan::call('testing:run-consult-eligibility', [
                    '--date-from' => $dateFrom,
                    '--date-to' => $dateTo,
                    '--limit' => self::BATCH_SIZE,
                    '--offset' => $offset,
                ]);

                $noRecordsFound = str_contains(Artisan::output(), 'No test results found matching criteria.');

                $offset += self::BATCH_SIZE;
                $page++;
            } while (! $noRecordsFound && $page < self::MAX_PAGES);

            Log::info('ConsultCallDailyEligibilitySweep: Completed', [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'pages_run' => $page,
                'stopped_reason' => $noRecordsFound ? 'no_more_records' : 'max_pages_reached',
            ]);

            return self::SUCCESS;
        } catch (Throwable $e) {
            Log::error('ConsultCallDailyEligibilitySweep: Failed', [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }
    }
}
