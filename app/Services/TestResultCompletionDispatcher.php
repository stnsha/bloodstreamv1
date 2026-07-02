<?php

namespace App\Services;

use App\Jobs\SendToAIServer;
use App\Models\TestResult;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class TestResultCompletionDispatcher
{
    protected OctopusApiService $octopusApi;

    protected ConsultCallEligibilityService $consultCallEligibilityService;

    public function __construct(OctopusApiService $octopusApi, ConsultCallEligibilityService $consultCallEligibilityService)
    {
        $this->octopusApi = $octopusApi;
        $this->consultCallEligibilityService = $consultCallEligibilityService;
    }

    /**
     * What happens once a TestResult is confirmed complete: AI review
     * dispatch and consult-call eligibility check. Every dependency is
     * derived from the TestResult itself, so this is safe to call from any
     * trigger point (a PDF-bearing delivery, an incremental panel-item-only
     * batch, or a comments batch) — not just the request that originally
     * completed the record.
     */
    public function dispatch(TestResult $testResult): void
    {
        $this->dispatchAIReview($testResult);
        $this->checkConsultCallEligibility($testResult);
    }

    protected function dispatchAIReview(TestResult $testResult): void
    {
        try {
            SendToAIServer::dispatch($testResult->id);
            Log::info('Dispatched test result to AI server queue', [
                'test_result_id' => $testResult->id,
                'lab_no' => $testResult->lab_no ?? null,
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to dispatch test result to AI server queue', [
                'test_result_id' => $testResult->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function checkConsultCallEligibility(TestResult $testResult): void
    {
        $patientId = $testResult->patient_id;

        try {
            // Gate 1: collected_date must be on or after 2026-03-08
            $collectedDateForConsult = $testResult->collected_date ?? $testResult->reported_date;
            $consultCutoffDate = Carbon::parse('2026-03-08')->startOfDay();

            if (! $collectedDateForConsult || Carbon::parse($collectedDateForConsult)->lt($consultCutoffDate)) {
                Log::info('Consult call skipped: collected date before 2026-03-08', [
                    'test_result_id' => $testResult->id,
                    'collected_date' => $collectedDateForConsult,
                ]);

                return;
            }

            // Gate 2: verify outlet eligibility via ref_id (Melaka, Johor, Kelantan).
            $refIdForConsult = $testResult->ref_id;

            if (! $refIdForConsult) {
                Log::info('Consult call skipped: no ref_id to verify eligible outlet', [
                    'test_result_id' => $testResult->id,
                    'patient_id' => $patientId,
                ]);

                return;
            }

            $labCodeForConsult = $testResult->doctor->lab->code ?? null;

            $eligibleCustomer = $this->octopusApi->eligibleConsultCallByOutlet($refIdForConsult, $labCodeForConsult);

            if (! $eligibleCustomer) {
                Log::info('Consult call skipped: not an eligible outlet or customer not found by ref_id', [
                    'test_result_id' => $testResult->id,
                    'ref_id' => $refIdForConsult,
                    'patient_id' => $patientId,
                ]);

                return;
            }

            $consultCustomerId = (int) $eligibleCustomer['customer_id'];
            $consultOutletId = isset($eligibleCustomer['outlet_id']) ? (int) $eligibleCustomer['outlet_id'] : null;

            $this->consultCallEligibilityService->checkAndCreate(
                $testResult, $patientId, $consultCustomerId, $consultOutletId
            );
        } catch (Throwable $e) {
            Log::error('Consult call eligibility check failed', [
                'test_result_id' => $testResult->id,
                'patient_id' => $patientId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }
}
