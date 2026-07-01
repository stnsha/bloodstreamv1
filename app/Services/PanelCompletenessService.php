<?php

namespace App\Services;

use App\Models\AIReview;
use App\Models\IncompleteTestResult;
use App\Models\PanelPanelProfile;
use App\Models\TestResult;
use App\Models\TestResultItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PanelCompletenessService
{
    /**
     * Minimum number of distinct panels that must be present in
     * test_result_items for a TestResult to be considered truly complete,
     * regardless of how many panels its linked profiles expect.
     */
    private const COMPLETE_PANEL_THRESHOLD = 8;

    /**
     * Compare the panels a TestResult is expected to have (via its linked
     * panel profiles) against the panels actually present in its
     * test_result_items, without making any changes.
     *
     * Completeness is decided by a flat threshold on actual_panel_count
     * (>= 8 is considered complete), not by matching every expected panel —
     * expected_panel_count/missing_panel_ids are returned for diagnostics
     * only.
     *
     * @return array{applicable: bool, is_complete: bool, expected_panel_count: int, actual_panel_count: int, missing_panel_ids: \Illuminate\Support\Collection}
     */
    public function evaluate(TestResult $testResult): array
    {
        $panelProfileIds = $testResult->testResultProfiles()->pluck('panel_profile_id')->unique();

        if ($panelProfileIds->isEmpty()) {
            return [
                'applicable' => false,
                'is_complete' => true,
                'expected_panel_count' => 0,
                'actual_panel_count' => 0,
                'missing_panel_ids' => collect(),
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

        return [
            'applicable' => true,
            'is_complete' => $actualPanelCount >= self::COMPLETE_PANEL_THRESHOLD,
            'expected_panel_count' => $expectedPanelIds->count(),
            'actual_panel_count' => $actualPanelCount,
            'missing_panel_ids' => $missingPanelIds,
        ];
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
