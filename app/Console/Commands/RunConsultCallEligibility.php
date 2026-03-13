<?php

namespace App\Console\Commands;

use App\Models\ConsultCallDetails;
use App\Models\Lab;
use App\Models\TestResult;
use App\Services\ConsultCallEligibilityService;
use App\Services\OctopusApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunConsultCallEligibility extends Command
{
    protected $signature = 'testing:run-consult-eligibility
        {--date-from=2026-02-01 : Start date filter (created_at and updated_at)}
        {--date-to=2026-02-10 : End date filter (created_at and updated_at)}
        {--limit=100 : Number of test results to process}
        {--offset=0 : Skip first N records}';

    protected $description = 'Run consult call eligibility checks on existing completed test results';

    public function handle(): int
    {
        $dateFrom = $this->option('date-from');
        $dateTo = $this->option('date-to');
        $limit = (int) $this->option('limit');
        $offset = (int) $this->option('offset');

        Log::info('RunConsultCallEligibility: Starting', [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'limit' => $limit,
            'offset' => $offset,
        ]);

        $this->info("Querying completed test results (date_from={$dateFrom}, date_to={$dateTo}, limit={$limit}, offset={$offset})");

        $testResults = TestResult::where('is_completed', true)
            ->whereNotNull('ref_id')
            ->whereBetween('created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->whereBetween('updated_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->orderBy('id', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        $total = $testResults->count();

        if ($total === 0) {
            $this->warn('No test results found matching criteria.');
            Log::info('RunConsultCallEligibility: No records found', [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'limit' => $limit,
                'offset' => $offset,
            ]);

            return self::SUCCESS;
        }

        $this->info("Found {$total} test results to process.");

        $octopusApi = app(OctopusApiService::class);
        $eligibilityService = app(ConsultCallEligibilityService::class);

        $counters = [
            'eligible' => 0,
            'healthy' => 0,
            'no_customer' => 0,
            'skipped_no_patient' => 0,
            'skipped_no_lab' => 0,
            'already_exists' => 0,
            'errors' => 0,
        ];

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($testResults as $testResult) {
            try {
                $result = $this->processTestResult($testResult, $octopusApi, $eligibilityService);
                $counters[$result]++;
            } catch (Throwable $e) {
                $counters['errors']++;
                Log::error('RunConsultCallEligibility: Error processing test result', [
                    'test_result_id' => $testResult->id,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
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
                ['Total records', $total],
                ['Eligible (CC created)', $counters['eligible']],
                ['Healthy (no match)', $counters['healthy']],
                ['No customer_id', $counters['no_customer']],
                ['Skipped (no patient)', $counters['skipped_no_patient']],
                ['Skipped (no lab)', $counters['skipped_no_lab']],
                ['Already exists', $counters['already_exists']],
                ['Errors', $counters['errors']],
            ]
        );

        Log::info('RunConsultCallEligibility: Completed', [
            'total' => $total,
            'counters' => $counters,
        ]);

        return self::SUCCESS;
    }

    /**
     * Process a single test result for consult call eligibility.
     *
     * @return string Counter key indicating the outcome
     */
    private function processTestResult(
        TestResult $testResult,
        OctopusApiService $octopusApi,
        ConsultCallEligibilityService $eligibilityService
    ): string {
        $patient = $testResult->patient;

        if (! $patient) {
            Log::info('RunConsultCallEligibility: Skipping, no patient', [
                'test_result_id' => $testResult->id,
                'patient_id'     => $testResult->patient_id,
            ]);

            return 'skipped_no_patient';
        }

        $doctor = $testResult->doctor;

        if (! $doctor || ! $doctor->lab_id) {
            Log::info('RunConsultCallEligibility: Skipping, no doctor or lab', [
                'test_result_id' => $testResult->id,
            ]);

            return 'skipped_no_lab';
        }

        $lab = Lab::find($doctor->lab_id);

        if (! $lab || ! $lab->code) {
            Log::info('RunConsultCallEligibility: Skipping, lab not found or no code', [
                'test_result_id' => $testResult->id,
                'lab_id' => $doctor->lab_id,
            ]);

            return 'skipped_no_lab';
        }

        $melakaCustomer = $octopusApi->customerMelakaByRefId($testResult->ref_id, $lab->code);

        if (! $melakaCustomer) {
            Log::info('RunConsultCallEligibility: No Melaka customer match for ref ID', [
                'test_result_id' => $testResult->id,
                'ref_id'         => $testResult->ref_id,
                'lab_code'       => $lab->code,
            ]);

            return 'no_customer';
        }

        $customerId = (int) $melakaCustomer['customer_id'];
        $outletId = isset($melakaCustomer['outlet_id']) ? (int) $melakaCustomer['outlet_id'] : null;

        $existedBefore = ConsultCallDetails::where('test_result_id', $testResult->id)->exists();

        $eligibilityService->checkAndCreate($testResult, $testResult->patient_id, $customerId, $outletId);

        if ($existedBefore) {
            return 'already_exists';
        }

        $existsNow = ConsultCallDetails::where('test_result_id', $testResult->id)->exists();

        if ($existsNow) {
            return 'eligible';
        }

        return 'healthy';
    }
}
