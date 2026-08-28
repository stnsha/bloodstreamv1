<?php

namespace App\Services;

use App\Jobs\SendToAIServer;
use App\Models\ClinicalCondition;
use App\Models\ConsultCallDetails;
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
     * Effective date for the add-on AI-review hold flow. On or after this date,
     * a completed result that matches an "AO" / "CC + AO" clinical condition has
     * its AI review withheld until a doctor releases it from the consult call.
     */
    const AI_REVIEW_HOLD_FROM = '2026-09-01';

    /**
     * Clinical condition types whose AI review is held for doctor release.
     */
    const HOLD_CONDITION_TYPES = ['AO', 'CC + AO'];

    /**
     * What happens once a TestResult is confirmed complete: consult-call
     * eligibility check, then AI review dispatch (unless the matched clinical
     * condition is an add-on type, in which case the review is held for a
     * doctor to release). Every dependency is derived from the TestResult
     * itself, so this is safe to call from any trigger point (a PDF-bearing
     * delivery, an incremental panel-item-only batch, or a comments batch) —
     * not just the request that originally completed the record.
     *
     * Consult-call eligibility runs first so the clinical condition it resolves
     * is persisted before the hold decision reads it. checkConsultCallEligibility
     * swallows its own errors, so a failure there never blocks the AI review.
     */
    public function dispatch(TestResult $testResult): void
    {
        $this->checkConsultCallEligibility($testResult);

        if ($this->shouldHoldAiReview($testResult)) {
            $this->holdAiReview($testResult);

            return;
        }

        $this->dispatchAIReview($testResult);
    }

    /**
     * True when the AI review for this result must wait for a doctor to release
     * it: the hold flow is in effect (processing on/after AI_REVIEW_HOLD_FROM),
     * the doctor has not already released it, and the latest consult-call detail
     * for this result carries a clinical condition of an add-on type.
     */
    protected function shouldHoldAiReview(TestResult $testResult): bool
    {
        if (now()->lt(Carbon::parse(self::AI_REVIEW_HOLD_FROM)->startOfDay())) {
            return false;
        }

        // Doctor already released this review; a later benign re-delivery must
        // not put it back on hold. Late/amended data clears this flag (see
        // PanelCompletenessService::revertReviewForLateData) so a genuine
        // re-review is re-held.
        if ($testResult->ai_review_released_at !== null) {
            return false;
        }

        $conditionId = ConsultCallDetails::where('test_result_id', $testResult->id)
            ->orderByDesc('id')
            ->value('clinical_condition_id');

        if (! $conditionId) {
            return false;
        }

        $type = ClinicalCondition::getCondition((int) $conditionId)['type'] ?? null;

        return in_array($type, self::HOLD_CONDITION_TYPES, true);
    }

    /**
     * Stamp the hold and skip AI review dispatch. Always clears
     * ai_review_released_at so a re-hold after amended data is a clean state.
     */
    protected function holdAiReview(TestResult $testResult): void
    {
        try {
            $testResult->forceFill([
                'ai_review_held_at' => now(),
                'ai_review_released_at' => null,
            ])->save();

            Log::info('AI review held pending doctor release (add-on clinical condition)', [
                'test_result_id' => $testResult->id,
                'lab_no' => $testResult->lab_no ?? null,
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to hold AI review; dispatching normally as fallback', [
                'test_result_id' => $testResult->id,
                'error' => $e->getMessage(),
            ]);

            $this->dispatchAIReview($testResult);
        }
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

            // Gate 1b: collected_date must fall within the last month up to today --
            // a rolling window instead of calendar month so a result collected on the
            // last day of a month but completed a few days into the next month still
            // enrolls, while a stale collected_date does not.
            $consultWindowStart = now()->subMonth()->startOfDay();
            $consultWindowEnd = now()->endOfDay();
            $parsedCollectedDate = Carbon::parse($collectedDateForConsult);

            if ($parsedCollectedDate->lt($consultWindowStart) || $parsedCollectedDate->gt($consultWindowEnd)) {
                Log::info('Consult call skipped: collected date outside the last month', [
                    'test_result_id' => $testResult->id,
                    'collected_date' => $collectedDateForConsult,
                    'window_start' => $consultWindowStart->toDateTimeString(),
                    'window_end' => $consultWindowEnd->toDateTimeString(),
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
