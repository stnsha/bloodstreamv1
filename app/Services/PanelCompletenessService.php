<?php

namespace App\Services;

use App\Models\AIReview;
use App\Models\IncompleteTestResult;
use App\Models\PanelPanelProfile;
use App\Models\PanelProfilesCount;
use App\Models\TestResult;
use App\Models\TestResultItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PanelCompletenessService
{
    /**
     * A record with this many distinct actual panels or more is always
     * treated as complete, even if its linked profiles' panel_profiles_count
     * sum is higher (an out-of-sync/never-manually-set expected count must
     * never keep a genuinely complete record flagged incomplete).
     */
    public const COMPLETE_PANEL_THRESHOLD = 8;

    protected OctopusApiService $octopusApi;

    public function __construct(OctopusApiService $octopusApi)
    {
        $this->octopusApi = $octopusApi;
    }

    /**
     * Compare the panels a TestResult is expected to have (via its linked
     * panel profiles) against the panels actually present in its
     * test_result_items, without making any changes.
     *
     * First cross-checks test_result_profiles count against ODB's invoice
     * item count (blood_test_sales/blood_test_item) — a mismatch means a
     * whole profile never arrived, regardless of panel count. If that check
     * matches (or can't be performed — fail-open), completeness is decided
     * by comparing actual_panel_count against expected_panel_count, where
     * expected_panel_count is the sum of panel_profiles_count.count for
     * every linked panel_profile_id (0 for any profile with no row there —
     * that table is manually maintained/correctable, not derived live from
     * panel_panel_profiles on every call). A record is complete if EITHER
     * actual_panel_count >= expected_panel_count (when expected is set) OR
     * actual_panel_count >= COMPLETE_PANEL_THRESHOLD — the threshold acts as
     * a ceiling so a stale/never-synced expected count higher than what's
     * actually achievable can never keep a record incomplete forever.
     * missing_panel_ids is still derived from panel_panel_profiles for
     * diagnostic purposes only.
     *
     * @return array{applicable: bool, is_complete: bool, expected_panel_count: int, actual_panel_count: int, missing_panel_ids: \Illuminate\Support\Collection, invoice_item_count: int|null, test_result_profiles_count: int}
     */
    public function evaluate(TestResult $testResult): array
    {
        $panelProfileIds = $testResult->testResultProfiles()->pluck('panel_profile_id')->unique();
        $testResultProfilesCount = $panelProfileIds->count();

        if ($panelProfileIds->isEmpty()) {
            return [
                'applicable' => false,
                'is_complete' => true,
                'expected_panel_count' => 0,
                'actual_panel_count' => 0,
                'missing_panel_ids' => collect(),
                'invoice_item_count' => null,
                'test_result_profiles_count' => $testResultProfilesCount,
            ];
        }

        $expectedPanelIds = PanelPanelProfile::whereIn('panel_profile_id', $panelProfileIds)
            ->pluck('panel_id')
            ->unique();

        $actualPanelIds = TestResultItem::where('test_result_id', $testResult->id)
            ->with('panelPanelItem')
            ->get()
            ->pluck('panelPanelItem.panel_id')
            ->filter()
            ->unique();

        $missingPanelIds = $expectedPanelIds->diff($actualPanelIds)->values();
        $actualPanelCount = $actualPanelIds->count();
        $expectedPanelCount = (int) PanelProfilesCount::whereIn('panel_profile_id', $panelProfileIds)->sum('count');

        $invoiceItemCount = $this->fetchInvoiceItemCount($testResult);

        if ($invoiceItemCount !== null && $invoiceItemCount !== $testResultProfilesCount) {
            return [
                'applicable' => true,
                'is_complete' => false,
                'expected_panel_count' => $expectedPanelCount,
                'actual_panel_count' => $actualPanelCount,
                'missing_panel_ids' => $missingPanelIds,
                'invoice_item_count' => $invoiceItemCount,
                'test_result_profiles_count' => $testResultProfilesCount,
            ];
        }

        // expected_panel_count = 0 means panel_profiles_count has no row for this
        // profile yet (not manually populated) — on its own that must not trivially
        // pass, so it only counts via the >= COMPLETE_PANEL_THRESHOLD ceiling below,
        // same as any record whose expected count is set but exceeds what's
        // actually achievable.
        $isComplete = $actualPanelCount >= self::COMPLETE_PANEL_THRESHOLD
            || ($expectedPanelCount > 0 && $actualPanelCount >= $expectedPanelCount);

        return [
            'applicable' => true,
            'is_complete' => $isComplete,
            'expected_panel_count' => $expectedPanelCount,
            'actual_panel_count' => $actualPanelCount,
            'missing_panel_ids' => $missingPanelIds,
            'invoice_item_count' => $invoiceItemCount,
            'test_result_profiles_count' => $testResultProfilesCount,
        ];
    }

    /**
     * Fetch the ODB invoice item count for this TestResult via OctopusApiService.
     * Fail-open: any lookup failure (no ref_id/icno, ODB unreachable, no match)
     * returns null so evaluate() falls back to the panel-count threshold alone.
     */
    private function fetchInvoiceItemCount(TestResult $testResult): ?int
    {
        try {
            $labCode = $testResult->doctor->lab->code ?? null;
            $rawRefId = $testResult->ref_id;
            $refId = null;

            if ($rawRefId && $labCode && stripos($rawRefId, $labCode) === 0) {
                $refId = substr($rawRefId, strlen($labCode));
            }

            $icno = $testResult->patient->icno ?? '';
            $referenceDate = $testResult->collected_date ?? $testResult->reported_date;

            if (!$refId && !$icno) {
                return null;
            }

            $month = $referenceDate ? Carbon::parse($referenceDate)->month : 0;
            $year = $referenceDate ? Carbon::parse($referenceDate)->year : 0;

            return $this->octopusApi->getBloodTestItemCount($refId, $icno, $month, $year);
        } catch (Throwable $e) {
            Log::warning('PanelCompletenessService: invoice item count lookup failed, falling back to panel-count threshold only', [
                'test_result_id' => $testResult->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Verify that a TestResult marked as completed actually has at least
     * COMPLETE_PANEL_THRESHOLD distinct panels present. If not, revert
     * is_completed to false and record the mismatch for later investigation.
     *
     * @return bool true if the TestResult is complete (or not applicable/not
     *              currently marked complete) and safe to proceed with,
     *              false if it was found incomplete and should be skipped.
     */
    public function checkAndHandle(TestResult $testResult): bool
    {
        if (! $testResult->is_completed) {
            return true;
        }

        $result = $this->evaluate($testResult);

        if (! $result['applicable'] || $result['is_complete']) {
            return true;
        }

        $expectedPanelCount = $result['expected_panel_count'];
        $actualPanelCount = $result['actual_panel_count'];
        $wasReviewed = $testResult->is_reviewed;

        try {
            DB::beginTransaction();

            $testResult->is_completed = false;
            $testResult->is_reviewed = false;
            $testResult->save();

            $existingAiReview = AIReview::where('test_result_id', $testResult->id)->first();

            if ($existingAiReview) {
                $existingAiReview->delete();
            }

            IncompleteTestResult::updateOrCreate(
                ['test_result_id' => $testResult->id],
                [
                    'expected_panel_count' => $expectedPanelCount,
                    'actual_panel_count' => $actualPanelCount,
                    'was_reviewed' => $wasReviewed,
                    'ai_review_id' => $existingAiReview->id ?? null,
                ]
            );

            DB::commit();

            Log::warning('PanelCompletenessService: incomplete panel data detected, is_completed reverted', [
                'test_result_id' => $testResult->id,
                'expected_panel_count' => $expectedPanelCount,
                'actual_panel_count' => $actualPanelCount,
            ]);
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('PanelCompletenessService: failed to record incomplete test result', [
                'test_result_id' => $testResult->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        return false;
    }

    /**
     * Undo a previous checkAndHandle() revert for a TestResult recorded in
     * incomplete_test_results. Restores is_completed=true, restores
     * is_reviewed to whatever it was before the revert, restores the
     * soft-deleted ai_reviews row (if any), and removes the
     * incomplete_test_results row. This does NOT re-evaluate current panel
     * data — it is a plain undo of the original revert, not a completeness
     * recheck.
     *
     * @return bool true if a matching incomplete_test_results row was found
     *              and undone, false if there was nothing to undo.
     */
    public function undo(TestResult $testResult): bool
    {
        $incompleteRecord = IncompleteTestResult::where('test_result_id', $testResult->id)->first();

        if (! $incompleteRecord) {
            return false;
        }

        try {
            DB::beginTransaction();

            $testResult->is_completed = true;
            $testResult->is_reviewed = $incompleteRecord->was_reviewed;
            $testResult->save();

            if ($incompleteRecord->ai_review_id) {
                AIReview::withTrashed()->where('id', $incompleteRecord->ai_review_id)->restore();
            }

            $incompleteRecord->delete();

            DB::commit();

            Log::info('PanelCompletenessService: incomplete flag undone, test result restored to original state', [
                'test_result_id' => $testResult->id,
                'was_reviewed' => $incompleteRecord->was_reviewed,
                'ai_review_id' => $incompleteRecord->ai_review_id,
            ]);
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('PanelCompletenessService: failed to undo incomplete test result', [
                'test_result_id' => $testResult->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        return true;
    }
}
