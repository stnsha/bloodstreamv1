<?php

namespace App\Console\Commands;

use App\Models\ConsultCallDetails;
use App\Models\TestResult;
use App\Services\ConsultCallEligibilityService;
use App\Services\OctopusApiService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunConsultCallEligibility extends Command
{
    protected $signature = 'testing:run-consult-eligibility
        {--date-from=2026-03-07 : Start date filter (collected_date)}
        {--date-to=2026-04-05 : End date filter (collected_date)}
        {--limit=100 : Number of test results to process}
        {--offset=0 : Skip first N records}
        {--dry-run : Simulate eligibility checks without writing to the database}';

    protected $description = 'Run consult call eligibility checks on existing completed test results (Melaka outlet, ref_id-based lookup)';

    public function handle(): int
    {
        $dateFrom = $this->option('date-from');
        $dateTo   = $this->option('date-to');
        $limit    = (int) $this->option('limit');
        $offset   = (int) $this->option('offset');
        $dryRun   = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN MODE — no records will be written to the database.');
        }

        Log::info('RunConsultCallEligibility: Starting', [
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
            'limit'     => $limit,
            'offset'    => $offset,
            'dry_run'   => $dryRun,
        ]);

        $this->info("Querying completed test results (date_from={$dateFrom}, date_to={$dateTo}, limit={$limit}, offset={$offset})");

        $testResults = TestResult::where('is_completed', true)
            ->whereNotNull('ref_id')
            ->whereBetween('collected_date', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->orderBy('id', 'asc')
            ->skip($offset)
            ->take($limit)
            ->get();

        $total = $testResults->count();

        if ($total === 0) {
            $this->warn('No test results found matching criteria.');
            Log::info('RunConsultCallEligibility: No records found', [
                'date_from' => $dateFrom,
                'date_to'   => $dateTo,
                'limit'     => $limit,
                'offset'    => $offset,
            ]);

            return self::SUCCESS;
        }

        $this->info("Found {$total} test results to process.");

        $octopusApi         = app(OctopusApiService::class);
        $eligibilityService = app(ConsultCallEligibilityService::class);

        $counters = [
            'eligible'           => 0,
            'healthy'            => 0,
            'no_customer'        => 0,
            'skipped_no_ref_id'  => 0,
            'skipped_no_patient' => 0,
            'already_exists'     => 0,
            'errors'             => 0,
        ];

        $outletEligibleCounts = [];

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($testResults as $testResult) {
            try {
                [$result, $outletId] = $this->processTestResult($testResult, $octopusApi, $eligibilityService, $dryRun);
                $counters[$result]++;

                if ($result === 'eligible' && $outletId !== null) {
                    $outletEligibleCounts[$outletId] = ($outletEligibleCounts[$outletId] ?? 0) + 1;
                }
            } catch (Throwable $e) {
                $counters['errors']++;
                Log::error('RunConsultCallEligibility: Error processing test result', [
                    'test_result_id' => $testResult->id,
                    'error'          => $e->getMessage(),
                    'file'           => $e->getFile(),
                    'line'           => $e->getLine(),
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('Processing complete.');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total records',          $total],
                ['Eligible (CC created)',  $counters['eligible']],
                ['Healthy (no match)',     $counters['healthy']],
                ['No customer (Melaka)',   $counters['no_customer']],
                ['Skipped (no ref_id)',    $counters['skipped_no_ref_id']],
                ['Skipped (no patient)',   $counters['skipped_no_patient']],
                ['Already exists',         $counters['already_exists']],
                ['Errors',                 $counters['errors']],
            ]
        );

        if (! empty($outletEligibleCounts)) {
            ksort($outletEligibleCounts);
            $outletRows = [];
            foreach ($outletEligibleCounts as $outletId => $count) {
                $outletRows[] = [$outletId, $count];
            }
            $this->info('Eligible cases by outlet:');
            $this->table(['Outlet ID', 'Total Eligible Cases'], $outletRows);
        }

        Log::info('RunConsultCallEligibility: Completed', [
            'total'    => $total,
            'counters' => $counters,
        ]);

        return self::SUCCESS;
    }

    /**
     * Process a single test result for Melaka consult call eligibility.
     *
     * Uses ref_id-based lookup (customerMelakaByRefId) — Melaka outlet only.
     *
     * @return array{0: string, 1: int|null} Counter key and outlet ID
     */
    private function processTestResult(
        TestResult $testResult,
        OctopusApiService $octopusApi,
        ConsultCallEligibilityService $eligibilityService,
        bool $dryRun = false
    ): array {
        if (! $testResult->patient_id) {
            Log::info('RunConsultCallEligibility: Skipping, no patient', [
                'test_result_id' => $testResult->id,
            ]);

            return ['skipped_no_patient', null];
        }

        if (! $testResult->ref_id) {
            Log::info('RunConsultCallEligibility: Skipping, no ref_id', [
                'test_result_id' => $testResult->id,
            ]);

            return ['skipped_no_ref_id', null];
        }

        $labCode  = $testResult->doctor->lab->code ?? null;
        $customer = $octopusApi->customerMelakaByRefId($testResult->ref_id, $labCode);

        if (! $customer) {
            Log::info('RunConsultCallEligibility: Not a Melaka customer or ref_id not found', [
                'test_result_id' => $testResult->id,
                'ref_id'         => $testResult->ref_id,
            ]);

            return ['no_customer', null];
        }

        $customerId = (int) $customer['customer_id'];
        $outletId   = isset($customer['outlet_id']) ? (int) $customer['outlet_id'] : null;

        $existedBefore = ConsultCallDetails::where('test_result_id', $testResult->id)->exists();

        if ($existedBefore) {
            return ['already_exists', $outletId];
        }

        if ($dryRun) {
            Log::info('RunConsultCallEligibility: DRY RUN — would enroll', [
                'test_result_id' => $testResult->id,
                'patient_id'     => $testResult->patient_id,
                'customer_id'    => $customerId,
                'outlet_id'      => $outletId,
            ]);

            return ['eligible', $outletId];
        }

        $eligibilityService->checkAndCreate($testResult, $testResult->patient_id, $customerId, $outletId);

        $existsNow = ConsultCallDetails::where('test_result_id', $testResult->id)->exists();

        if ($existsNow) {
            return ['eligible', $outletId];
        }

        return ['healthy', $outletId];
    }
}
