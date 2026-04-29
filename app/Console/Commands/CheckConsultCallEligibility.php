<?php

namespace App\Console\Commands;

use App\Models\ConsultCallDetails;
use App\Models\TestResult;
use App\Services\ConsultCallEligibilityService;
use App\Services\OctopusApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class CheckConsultCallEligibility extends Command
{
    protected $signature = 'testing:check-consult-eligibility
        {--test_result_id= : The ID of the test result to check}
        {--dry-run : Simulate without writing to the database}';

    protected $description = 'Check consult call eligibility for a single test result and show the reason if skipped';

    public function handle(): int
    {
        $id     = $this->option('test_result_id');
        $dryRun = (bool) $this->option('dry-run');

        if (! $id || ! is_numeric($id)) {
            $this->error('--test_result_id is required and must be a numeric value.');

            return self::FAILURE;
        }

        $id = (int) $id;

        Log::info('CheckConsultCallEligibility: Starting', ['test_result_id' => $id, 'dry_run' => $dryRun]);

        if ($dryRun) {
            $this->warn('DRY RUN MODE — no records will be written.');
        }

        $this->line("Checking test result ID: {$id}");
        $this->line('');

        $testResult = TestResult::find($id);

        if (! $testResult) {
            $this->error("Test result ID {$id} not found.");
            Log::info('CheckConsultCallEligibility: Test result not found', ['test_result_id' => $id]);

            return self::FAILURE;
        }

        $this->line('  is_completed  : ' . ($testResult->is_completed ? 'true' : 'false'));
        $this->line('  patient_id    : ' . ($testResult->patient_id ?? 'null'));
        $this->line('  ref_id        : ' . ($testResult->ref_id ?? 'null'));
        $this->line('  collected_date: ' . ($testResult->collected_date ?? 'null'));
        $this->line('');

        // Gate 1: is_completed
        if (! $testResult->is_completed) {
            $this->warn('SKIPPED: test result is not completed (is_completed = false)');
            Log::info('CheckConsultCallEligibility: Skipped - not completed', ['test_result_id' => $id]);

            return self::SUCCESS;
        }

        $this->line('  [PASS] is_completed = true');

        // Gate 2: patient_id
        if (! $testResult->patient_id) {
            $this->warn('SKIPPED: no patient linked (patient_id is null)');
            Log::info('CheckConsultCallEligibility: Skipped - no patient', ['test_result_id' => $id]);

            return self::SUCCESS;
        }

        $this->line("  [PASS] patient_id = {$testResult->patient_id}");

        // Gate 3: ref_id
        if (! $testResult->ref_id) {
            $this->warn('SKIPPED: ref_id is null');
            Log::info('CheckConsultCallEligibility: Skipped - no ref_id', ['test_result_id' => $id]);

            return self::SUCCESS;
        }

        $this->line("  [PASS] ref_id = {$testResult->ref_id}");

        // Gate 4: outlet eligibility
        $labCode    = $testResult->doctor->lab->code ?? null;
        $octopusApi = app(OctopusApiService::class);
        $customer   = $octopusApi->eligibleConsultCallByOutlet($testResult->ref_id, $labCode);

        if (! $customer) {
            $this->warn('SKIPPED: not an eligible outlet customer (ref_id=' . $testResult->ref_id . ', lab_code=' . ($labCode ?? 'null') . ')');
            Log::info('CheckConsultCallEligibility: Skipped - no eligible customer', [
                'test_result_id' => $id,
                'ref_id'         => $testResult->ref_id,
                'lab_code'       => $labCode,
            ]);

            return self::SUCCESS;
        }

        $customerId = (int) $customer['customer_id'];
        $outletId   = isset($customer['outlet_id']) ? (int) $customer['outlet_id'] : null;

        $this->line('  [PASS] Outlet check passed (customer_id=' . $customerId . ', outlet_id=' . ($outletId ?? 'null') . ')');

        // Gate 5: already exists
        $existedBefore = ConsultCallDetails::where('test_result_id', $id)->exists();

        if ($existedBefore) {
            $this->info('ALREADY EXISTS: a ConsultCallDetails record already exists for this test result.');
            Log::info('CheckConsultCallEligibility: Already exists', ['test_result_id' => $id]);

            return self::SUCCESS;
        }

        $this->line('  [PASS] No existing ConsultCallDetails record found.');
        $this->line('');

        if ($dryRun) {
            $this->info('DRY RUN: all gates passed — would proceed to eligibility evaluation. No record written.');
            Log::info('CheckConsultCallEligibility: Dry run stop', ['test_result_id' => $id]);

            return self::SUCCESS;
        }

        $this->line('  Running eligibility check...');

        try {
            $eligibilityService = app(ConsultCallEligibilityService::class);
            $eligibilityService->checkAndCreate($testResult, $testResult->patient_id, $customerId, $outletId);
        } catch (Throwable $e) {
            $this->error('ERROR during eligibility check: ' . $e->getMessage());
            Log::error('CheckConsultCallEligibility: Error', [
                'test_result_id' => $id,
                'error'          => $e->getMessage(),
                'file'           => $e->getFile(),
                'line'           => $e->getLine(),
            ]);

            return self::FAILURE;
        }

        $existsNow = ConsultCallDetails::where('test_result_id', $id)->exists();

        if ($existsNow) {
            $this->info('RESULT: ELIGIBLE — ConsultCall record was created.');
            Log::info('CheckConsultCallEligibility: Eligible', ['test_result_id' => $id]);
        } else {
            $this->warn('RESULT: NOT ELIGIBLE — no clinical condition matched (healthy patient).');
            Log::info('CheckConsultCallEligibility: Not eligible', ['test_result_id' => $id]);
        }

        return self::SUCCESS;
    }
}
