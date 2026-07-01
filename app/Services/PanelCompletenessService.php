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
     * Compare the panels a TestResult is expected to have (via its linked
     * panel profiles) against the panels actually present in its
     * test_result_items, without making any changes.
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

        return [
            'applicable' => true,
            'is_complete' => $missingPanelIds->isEmpty(),
            'expected_panel_count' => $expectedPanelIds->count(),
            'actual_panel_count' => $actualPanelIds->count(),
            'missing_panel_ids' => $missingPanelIds,
        ];
    }

    /**
     * Verify that a TestResult marked as completed actually has all panels
     * expected by its linked panel profiles. If panels are missing, revert
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

        try {
            DB::beginTransaction();

            $testResult->is_completed = false;
            $testResult->is_reviewed = false;
            $testResult->save();

            IncompleteTestResult::updateOrCreate(
                ['test_result_id' => $testResult->id],
                [
                    'expected_panel_count' => $expectedPanelCount,
                    'actual_panel_count' => $actualPanelCount,
                ]
            );

            AIReview::where('test_result_id', $testResult->id)->delete();

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
     * Re-evaluate a TestResult previously recorded in incomplete_test_results.
     * If all expected panels are now present, restore is_completed to true
     * and remove the incomplete_test_results row.
     *
     * @return bool true if the TestResult is now complete and was restored,
     *              false if it is still missing panels.
     */
    public function resolve(TestResult $testResult): bool
    {
        $result = $this->evaluate($testResult);

        if (! $result['applicable'] || ! $result['is_complete']) {
            return false;
        }

        try {
            DB::beginTransaction();

            $testResult->is_completed = true;
            $testResult->save();

            IncompleteTestResult::where('test_result_id', $testResult->id)->delete();

            DB::commit();

            Log::info('PanelCompletenessService: test result now complete, incomplete flag cleared', [
                'test_result_id' => $testResult->id,
            ]);
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('PanelCompletenessService: failed to resolve incomplete test result', [
                'test_result_id' => $testResult->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        return true;
    }
}
