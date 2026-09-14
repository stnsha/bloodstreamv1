<?php

namespace App\Console\Commands;

use App\Models\ConsultCallDetails;
use App\Models\TestResult;
use App\Services\ConsultCallEligibilityService;
use App\Services\OctopusApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class EnrollConsultCall extends Command
{
    protected $signature = 'consult-call:enroll
        {identifier : Test result ID or lab_no}';

    protected $description = 'Create/enroll a consult call for one test result: resolve outlet eligibility, run clinical condition evaluation, and enroll';

    public function handle(): int
    {
        $identifier = (string) $this->argument('identifier');

        $testResult = is_numeric($identifier)
            ? TestResult::find((int) $identifier)
            : null;

        if (! $testResult) {
            $testResult = TestResult::where('lab_no', $identifier)->first();
        }

        if (! $testResult) {
            $this->error("Test result not found for identifier: {$identifier}");
            Log::warning('EnrollConsultCall: test result not found', ['identifier' => $identifier]);

            return self::FAILURE;
        }

        Log::info('EnrollConsultCall: Starting', [
            'test_result_id' => $testResult->id,
            'lab_no' => $testResult->lab_no,
            'identifier' => $identifier,
        ]);

        if (! $testResult->patient_id) {
            $this->warn('SKIPPED: no patient linked to this test result (patient_id is null).');
            Log::info('EnrollConsultCall: Skipped, no patient', ['test_result_id' => $testResult->id]);

            return self::SUCCESS;
        }

        if (! $testResult->ref_id) {
            $this->warn('SKIPPED: ref_id is null, cannot verify outlet eligibility.');
            Log::info('EnrollConsultCall: Skipped, no ref_id', ['test_result_id' => $testResult->id]);

            return self::SUCCESS;
        }

        try {
            $labCode = $testResult->doctor->lab->code ?? null;
            $octopusApi = app(OctopusApiService::class);
            $customer = $octopusApi->eligibleConsultCallByOutlet($testResult->ref_id, $labCode);

            if (! $customer) {
                $this->warn('SKIPPED: not an eligible outlet customer (ref_id=' . $testResult->ref_id . ', lab_code=' . ($labCode ?? 'null') . ').');
                Log::info('EnrollConsultCall: Skipped, not an eligible outlet customer', [
                    'test_result_id' => $testResult->id,
                    'ref_id' => $testResult->ref_id,
                    'lab_code' => $labCode,
                ]);

                return self::SUCCESS;
            }

            $customerId = (int) $customer['customer_id'];
            $outletId = isset($customer['outlet_id']) ? (int) $customer['outlet_id'] : null;

            app(ConsultCallEligibilityService::class)->checkAndCreate(
                $testResult,
                $testResult->patient_id,
                $customerId,
                $outletId
            );

            $detail = ConsultCallDetails::where('test_result_id', $testResult->id)
                ->orderByDesc('id')
                ->first();

            if ($detail) {
                $this->info("ELIGIBLE: consult call detail created/updated (consult_call_id={$detail->consult_call_id}, clinical_condition_id={$detail->clinical_condition_id}).");
                Log::info('EnrollConsultCall: Completed, consult call detail present', [
                    'test_result_id' => $testResult->id,
                    'consult_call_id' => $detail->consult_call_id,
                    'clinical_condition_id' => $detail->clinical_condition_id,
                ]);
            } else {
                $this->warn('No consult call detail was created (patient healthy with no existing consult call, or re-enrollment gates blocked it — check logs).');
                Log::info('EnrollConsultCall: Completed, no consult call detail created', ['test_result_id' => $testResult->id]);
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('ERROR: ' . $e->getMessage());
            Log::error('EnrollConsultCall: Failed', [
                'test_result_id' => $testResult->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return self::FAILURE;
        }
    }
}
