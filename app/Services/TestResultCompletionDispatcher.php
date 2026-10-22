<?php

namespace App\Services;

use App\Jobs\SendToAIServer;
use App\Models\AIReview;
use App\Models\ClinicalCondition;
use App\Models\ConsultCallDetails;
use App\Models\IncompleteTestResult;
use App\Models\TestResult;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class TestResultCompletionDispatcher
{
    protected OctopusApiService $octopusApi;

    protected ConsultCallEligibilityService $consultCallEligibilityService;

    protected AddOnResultLinkService $addOnResultLinkService;

    public function __construct(
        OctopusApiService $octopusApi,
        ConsultCallEligibilityService $consultCallEligibilityService,
        AddOnResultLinkService $addOnResultLinkService
    ) {
        $this->octopusApi = $octopusApi;
        $this->consultCallEligibilityService = $consultCallEligibilityService;
        $this->addOnResultLinkService = $addOnResultLinkService;
    }

    /**
     * Effective date for the AI-review hold flow. On or after this date, a
     * completed result enrolled into a consult call ("CC" / "CC + AO" / "AO"
     * clinical condition) has its AI review withheld until it is released from
     * the consult call, so neither the doctor review nor the report flows to the
     * outlet before the ConsultCall update.
     */
    const AI_REVIEW_HOLD_FROM = '2026-09-16';

    /**
     * Clinical condition types whose AI review is held for release.
     */
    const HOLD_CONDITION_TYPES = ['CC', 'CC + AO', 'AO'];

    /**
     * What happens once a TestResult is confirmed complete: consult-call
     * eligibility check, then AI review dispatch (unless the result is enrolled
     * under a CC / CC + AO / AO clinical condition, in which case the review is
     * held until released from the consult call). Every dependency is derived from the TestResult
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
        $this->linkAddOnResult($testResult);

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
     * for this result carries a clinical condition of a held type.
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

        // Read the type directly: getCondition() only knows active conditions, and
        // an enrolled result keeps its condition after that condition is deactivated.
        $type = ClinicalCondition::whereKey((int) $conditionId)->value('type');

        return in_array($type, self::HOLD_CONDITION_TYPES, true);
    }

    /**
     * Stamp the hold and skip AI review dispatch. Always clears
     * ai_review_released_at so a re-hold after amended data is a clean state.
     *
     * A held result (CC / CC + AO / AO) stays is_completed=0 / is_reviewed=0 until
     * the consult call releases it (releaseHeldAiReview()): an add-on bought on the
     * call runs on the same blood sample, so its panels arrive later under the same
     * lab number. Every later delivery re-runs completeness and lands here again,
     * since the delivery jobs re-check any result that is not completed and reviewed.
     */
    protected function holdAiReview(TestResult $testResult): void
    {
        try {
            $testResult->forceFill([
                'ai_review_held_at' => now(),
                'ai_review_released_at' => null,
                'is_completed' => false,
                'is_reviewed' => false,
            ])->save();

            Log::info('AI review held pending consult call release', [
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

    /**
     * Release a held AI review from the consult call (Release Doctor Review = Yes,
     * or the case is closed): stamp ai_review_released_at and promote the result to
     * is_completed=1 / is_reviewed=0 so SendToAIServer picks it up. A result that a
     * later delivery marked incomplete (incomplete_test_results row) stays
     * is_completed=0; the next completeness pass completes it and, being released,
     * dispatches the AI review instead of holding it again. The caller dispatches
     * SendToAIServer.
     *
     * A result whose AI review already COMPLETED before the hold (backfilled holds,
     * or reviews that finished before the hold was stamped) keeps is_reviewed=1:
     * SendToAIServer and ai:dispatch-unreviewed-async both skip a result with a
     * COMPLETED review, so resetting is_reviewed would leave it unreviewed forever.
     * Amended data marks that review STALE_* first, so a genuine re-review still runs.
     */
    public function releaseHeldAiReview(TestResult $testResult): void
    {
        $stillIncomplete = IncompleteTestResult::where('test_result_id', $testResult->id)->exists();

        $hasCompletedReview = ! $stillIncomplete
            && AIReview::where('test_result_id', $testResult->id)
                ->where('processing_status', 'COMPLETED')
                ->exists();

        $testResult->forceFill([
            'ai_review_released_at' => now(),
            'is_completed' => ! $stillIncomplete,
            'is_reviewed' => $hasCompletedReview,
        ])->save();

        Log::info('AI review released from consult call', [
            'test_result_id' => $testResult->id,
            'lab_no' => $testResult->lab_no ?? null,
            'is_completed' => ! $stillIncomplete,
            'is_reviewed' => $hasCompletedReview,
        ]);
    }

    /**
     * Attach an Add-On lab result to the consult call whose Add-On invoice
     * produced it. Runs before and independent of the consult-call eligibility
     * gates (date window, outlet, panel completeness), which an add-on-only
     * result would otherwise fail. Swallows and logs its own errors.
     */
    protected function linkAddOnResult(TestResult $testResult): void
    {
        try {
            $this->addOnResultLinkService->linkFromTestResult($testResult);
        } catch (Throwable $e) {
            Log::error('Add-on result link failed', [
                'test_result_id' => $testResult->id,
                'ref_id' => $testResult->ref_id,
                'error' => $e->getMessage(),
            ]);
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

    /**
     * Run the live-flow consult-call eligibility check (date gates, outlet
     * eligibility, clinical conditions) and auto-enroll when eligible. Public
     * so manual re-run tooling (test-result:rerun-review) applies exactly the
     * same gates as a live delivery. Swallows and logs its own errors.
     */
    public function checkConsultCallEligibility(TestResult $testResult): void
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
