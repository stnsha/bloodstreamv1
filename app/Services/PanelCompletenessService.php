<?php

namespace App\Services;

use App\Constants\Innoquest\PanelPanelItem as PanelPanelItemConstants;
use App\Models\AIReview;
use App\Models\IncompleteTestResult;
use App\Models\Panel;
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

    /**
     * Human-readable labels for the special-test formulas' raw
     * test_result_items inputs, used to build missing_details when
     * missingSpecialTestParameters() finds a gap. Platelets is handled
     * separately since it has two alternate item ids. Glucose Fasting Type
     * is deliberately NOT required here — when absent, calculation defaults
     * it to 'Random' (non-fasting) rather than blocking completeness.
     */
    private const SPECIAL_TEST_PARAMETER_LABELS = [
        PanelPanelItemConstants::CRI_I => 'CRI-I',
        PanelPanelItemConstants::CRI_II => 'CRI-II',
        PanelPanelItemConstants::AIP => 'AIP',
        PanelPanelItemConstants::TOTAL_CHOLESTEROL => 'Total Cholesterol',
        PanelPanelItemConstants::HDL => 'HDL',
        PanelPanelItemConstants::AST => 'AST',
        PanelPanelItemConstants::ALT => 'ALT',
        PanelPanelItemConstants::ALBUMIN => 'Albumin',
    ];

    /**
     * Total number of distinct special-test parameter slots checked by
     * missingSpecialTestParameters(): the 8 labeled entries above plus
     * Platelets (its two alternate item ids count as a single slot).
     */
    private const SPECIAL_TEST_TOTAL_PARAMETERS = 9;

    protected OctopusApiService $octopusApi;

    protected TestResultCompilerService $testResultCompilerService;

    public function __construct(OctopusApiService $octopusApi, TestResultCompilerService $testResultCompilerService)
    {
        $this->octopusApi = $octopusApi;
        $this->testResultCompilerService = $testResultCompilerService;
    }

    /**
     * Compare the panels a TestResult is expected to have (via its linked
     * panel profiles) against the panels actually present in its
     * test_result_items, without making any changes.
     *
     * ODB's blood_test_item rows for an invoice mix two kinds of line item:
     * standalone panel purchases (a fixed, known item_code list — see
     * bloodTestItemCount.php) and package/profile purchases (everything
     * else). fetchInvoiceBreakdown() splits the invoice into
     * panel_item_count and profile_item_count so each branch below compares
     * against the right one instead of one combined raw count.
     *
     * First cross-checks test_result_profiles count against
     * profile_item_count — a mismatch usually means a whole profile never
     * arrived, UNLESS actual_panel_count already satisfies the panel-count
     * completeness check below (>= expected_panel_count, when set, OR >=
     * COMPLETE_PANEL_THRESHOLD), in which case that overrides the mismatch
     * (a record that already has as many panels as expected, or enough to
     * clear the ceiling, is treated as stronger evidence than a noisy
     * invoice count). If the check matches, can't be performed (fail-open),
     * or is overridden by the panel-count check, completeness is decided by
     * comparing actual_panel_count against expected_panel_count, where
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
     * If the record has no linked panel_profile at all (Innoquest sent no
     * PackageCode), there's no expected count from PanelProfilesCount to
     * compare against. If profile_item_count > 0, a package/profile item was
     * billed but its profile link never arrived — incomplete, same as the
     * profiled branch's mismatch. Otherwise (profile_item_count === 0) this
     * is a genuine a-la-carte order — possibly more than one standalone
     * panel bought in the same visit — so panel_item_count itself becomes
     * expected_panel_count, and the record is complete iff actual_panel_count
     * >= max(expected_panel_count, 1) (a PDF arriving with zero actual
     * panels can never be marked complete).
     *
     * @return array{applicable: bool, is_complete: bool, expected_panel_count: int, actual_panel_count: int, missing_panel_ids: \Illuminate\Support\Collection, invoice_item_count: int|null, test_result_profiles_count: int}
     */
    public function evaluate(TestResult $testResult): array
    {
        $panelProfileIds = $testResult->testResultProfiles()->pluck('panel_profile_id')->unique();
        $testResultProfilesCount = $panelProfileIds->count();

        if ($panelProfileIds->isEmpty()) {
            $actualPanelCount = TestResultItem::where('test_result_id', $testResult->id)
                ->with('panelPanelItem')
                ->get()
                ->pluck('panelPanelItem.panel_id')
                ->filter()
                ->unique()
                ->count();

            $breakdown = $this->fetchInvoiceBreakdown($testResult);

            if ($breakdown === null) {
                return [
                    'applicable' => true,
                    'is_complete' => $actualPanelCount >= 1,
                    'expected_panel_count' => 0,
                    'actual_panel_count' => $actualPanelCount,
                    'missing_panel_ids' => collect(),
                    'invoice_item_count' => null,
                    'test_result_profiles_count' => $testResultProfilesCount,
                ];
            }

            if ($breakdown['profile_item_count'] > 0) {
                return [
                    'applicable' => true,
                    'is_complete' => false,
                    'expected_panel_count' => 0,
                    'actual_panel_count' => $actualPanelCount,
                    'missing_panel_ids' => collect(),
                    'invoice_item_count' => $breakdown['profile_item_count'],
                    'test_result_profiles_count' => $testResultProfilesCount,
                ];
            }

            $expectedPanelCount = $breakdown['panel_item_count'];

            return [
                'applicable' => true,
                'is_complete' => $actualPanelCount >= max($expectedPanelCount, 1),
                'expected_panel_count' => $expectedPanelCount,
                'actual_panel_count' => $actualPanelCount,
                'missing_panel_ids' => collect(),
                'invoice_item_count' => $breakdown['profile_item_count'],
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

        $breakdown = $this->fetchInvoiceBreakdown($testResult);
        $invoiceItemCount = $breakdown['profile_item_count'] ?? null;
        $invoiceMismatch = $invoiceItemCount !== null && $invoiceItemCount !== $testResultProfilesCount;

        // expected_panel_count = 0 means panel_profiles_count has no row for this
        // profile yet (not manually populated) — on its own that must not trivially
        // pass, so it only counts via the >= COMPLETE_PANEL_THRESHOLD ceiling below,
        // same as any record whose expected count is set but exceeds what's
        // actually achievable.
        $meetsPanelThreshold = $actualPanelCount >= self::COMPLETE_PANEL_THRESHOLD
            || ($expectedPanelCount > 0 && $actualPanelCount >= $expectedPanelCount);

        // profile_item_count (bloodTestItemCount.php) is a raw blood_test_item ROW
        // count and can over-count relative to the true number of distinct billed
        // profiles when a package produces more than one line item on the invoice.
        // Before trusting a raw-count mismatch enough to block the panel-threshold
        // override below, confirm it against the deduplicated DISTINCT item_code
        // package count from bloodTestItemPackageNames.php — only called here (not
        // on every check) since it's a heavier ODB lookup. An empty result is
        // ambiguous (lookup failure vs genuinely nothing) and is treated as
        // inconclusive, same fail-open behavior as fetchInvoiceBreakdown().
        $effectiveMismatch = $invoiceMismatch;
        $confirmedMismatch = false;

        if ($invoiceMismatch) {
            $packageNames = $this->getInvoicePackageNames($testResult);

            if (! empty($packageNames)) {
                $confirmedMismatch = count($packageNames) !== $testResultProfilesCount;
                $effectiveMismatch = $confirmedMismatch;
            }
        }

        // A confirmed mismatch (backed by the reliable distinct package count)
        // means a whole profile really is missing — the panel-count threshold
        // below has no visibility into a profile that never arrived at all, so it
        // must never be allowed to override this.
        if ($confirmedMismatch) {
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

        if ($effectiveMismatch && ! $meetsPanelThreshold) {
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

        return [
            'applicable' => true,
            'is_complete' => $meetsPanelThreshold,
            'expected_panel_count' => $expectedPanelCount,
            'actual_panel_count' => $actualPanelCount,
            'missing_panel_ids' => $missingPanelIds,
            'invoice_item_count' => $invoiceItemCount,
            'test_result_profiles_count' => $testResultProfilesCount,
        ];
    }

    /**
     * Fetch the ODB invoice breakdown (panel_item_count/profile_item_count) for
     * this TestResult via OctopusApiService. Fail-open: any lookup failure (no
     * ref_id/icno, ODB unreachable, no match) returns null so evaluate() falls
     * back to the panel-count threshold alone.
     *
     * @return array{panel_item_count: int, profile_item_count: int}|null
     */
    private function fetchInvoiceBreakdown(TestResult $testResult): ?array
    {
        try {
            $params = $this->resolveInvoiceLookupParams($testResult);

            if ($params === null) {
                return null;
            }

            return $this->octopusApi->getBloodTestItemBreakdown($params['ref_id'], $params['icno'], $params['month'], $params['year']);
        } catch (Throwable $e) {
            Log::warning('PanelCompletenessService: invoice breakdown lookup failed, falling back to panel-count threshold only', [
                'test_result_id' => $testResult->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Fetch the ODB package/profile names billed on the invoice linked to this
     * TestResult, for display in the invoice_mismatch missing_details column of
     * the incomplete-results CSV export. Deliberately not called during
     * evaluate()/resolve() - it hits a separate ODB endpoint
     * (bloodTestItemPackageNames.php) with an extra `simple` table join per
     * item_code, which is only worth paying for on demand (CSV export),
     * not on every panel completeness check. Fail-open: any lookup failure
     * returns [].
     *
     * @return array<string>
     */
    public function getInvoicePackageNames(TestResult $testResult): array
    {
        try {
            $params = $this->resolveInvoiceLookupParams($testResult);

            if ($params === null) {
                return [];
            }

            return $this->octopusApi->getBloodTestItemPackageNames($params['ref_id'], $params['icno'], $params['month'], $params['year']);
        } catch (Throwable $e) {
            Log::warning('PanelCompletenessService: invoice package names lookup failed', [
                'test_result_id' => $testResult->id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Build the ref_id/icno/month/year params used to look up a TestResult's
     * ODB invoice, shared by fetchInvoiceBreakdown() and
     * getInvoicePackageNames() so both hit the same invoice.
     *
     * @return array{ref_id: string|null, icno: string, month: int, year: int}|null null when neither ref_id nor icno is available
     */
    private function resolveInvoiceLookupParams(TestResult $testResult): ?array
    {
        $labCode = $testResult->doctor->lab->code ?? null;
        $rawRefId = $testResult->ref_id;
        $refId = null;

        if ($rawRefId && $labCode && stripos($rawRefId, $labCode) === 0) {
            $refId = substr($rawRefId, strlen($labCode));
        }

        $icno = $testResult->patient->icno ?? '';
        $referenceDate = $testResult->collected_date ?? $testResult->reported_date;

        if (! $refId && ! $icno) {
            return null;
        }

        return [
            'ref_id' => $refId,
            'icno' => $icno,
            'month' => $referenceDate ? Carbon::parse($referenceDate)->month : 0,
            'year' => $referenceDate ? Carbon::parse($referenceDate)->year : 0,
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

        $reason = $this->resolveIncompleteReason($result);
        $this->recordIncomplete($testResult, $result, $reason, $this->missingDetailsForReason($testResult, $result, $reason));

        return false;
    }

    /**
     * Derive why a record is incomplete from evaluate()'s result, so every
     * caller that records an incomplete_test_results row classifies the
     * reason identically.
     */
    private function resolveIncompleteReason(array $result): string
    {
        if ($result['invoice_item_count'] !== null && $result['invoice_item_count'] !== $result['test_result_profiles_count']) {
            return 'invoice_mismatch';
        }

        return 'panel_count';
    }

    /**
     * Build a short human-readable explanation of what's missing for a
     * given evaluate()-derived reason. 'special_tests_missing_parameters'
     * is built directly in evaluateFull() instead, since that's the one
     * place with the missing-parameter list already in hand.
     */
    private function missingDetailsForReason(TestResult $testResult, array $result, string $reason): ?string
    {
        if ($reason === 'panel_count' && $result['missing_panel_ids']->isNotEmpty()) {
            $names = Panel::whereIn('id', $result['missing_panel_ids'])->pluck('name');

            return 'Missing panels: '.$names->implode(', ');
        }

        if ($reason === 'invoice_mismatch') {
            return "ODB profile/package items: {$result['invoice_item_count']}, test_result_profiles: {$result['test_result_profiles_count']}";
        }

        return null;
    }

    /**
     * Revert is_completed to false (if currently true) and record the
     * mismatch in incomplete_test_results for later investigation. Shared by
     * checkAndHandle() (demoting a previously-complete record) and resolve()
     * (recording why a record never reached completeness in the first
     * place, including the special-test-parameters reason which has no
     * evaluate()-derived equivalent).
     */
    private function recordIncomplete(TestResult $testResult, array $result, string $reason, ?string $missingDetails = null): void
    {
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
                    'reason' => $reason,
                    'missing_details' => $missingDetails,
                ]
            );

            DB::commit();

            Log::warning('PanelCompletenessService: incomplete panel data detected, is_completed reverted', [
                'test_result_id' => $testResult->id,
                'expected_panel_count' => $expectedPanelCount,
                'actual_panel_count' => $actualPanelCount,
                'reason' => $reason,
                'missing_details' => $missingDetails,
            ]);
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('PanelCompletenessService: failed to record incomplete test result', [
                'test_result_id' => $testResult->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Which of the raw test_result_items inputs the 7 special-test formulas
     * need are missing, as human-readable labels (empty array = nothing
     * missing, full list = none of the SPECIAL_TEST_TOTAL_PARAMETERS slots
     * matched). BMI (used only by NFS) comes from the external MyHealth
     * service, not test_result_items, and is deliberately excluded here —
     * its absence must never block completeness. Platelets has two
     * alternate item ids (primary, then a fallback); either one satisfies
     * the requirement, matching getPlateletsValue()'s existing fallback.
     *
     * Callers must not treat a full missing list on its own as blocking:
     * see evaluateFull(), which only blocks completeness on a *partial*
     * match (at least one parameter present, but not all) — a profile with
     * zero matched parameters (e.g. ALEMO) simply isn't special-test
     * eligible.
     */
    private function missingSpecialTestParameters(TestResult $testResult): array
    {
        $existingIds = TestResultItem::where('test_result_id', $testResult->id)
            ->whereIn('panel_panel_item_id', PanelPanelItemConstants::PANEL_PANEL_ITEM_IDS)
            ->pluck('panel_panel_item_id')
            ->unique();

        $missing = [];

        foreach (self::SPECIAL_TEST_PARAMETER_LABELS as $id => $label) {
            if (! $existingIds->contains($id)) {
                $missing[] = $label;
            }
        }

        if (! $existingIds->contains(PanelPanelItemConstants::PLATELETS)
            && ! $existingIds->contains(PanelPanelItemConstants::PLATELETS_ALT)) {
            $missing[] = 'Platelets';
        }

        return $missing;
    }

    /**
     * Read-only a+b+c completeness verdict — everything resolve() decides,
     * without writing anything. Layers the special-test-parameter gate (c)
     * on top of evaluate()'s a+b result: not applicable when there's no
     * linked profile (special tests aren't meaningful for a-la-carte
     * tests). When a profile is linked, the gate only blocks completeness
     * on a *partial* match — at least one of the SPECIAL_TEST_TOTAL_PARAMETERS
     * slots present but not all of them (reason
     * 'special_tests_missing_parameters'). A profile with zero matched
     * parameters (e.g. ALEMO) is never blocked here — it simply isn't a
     * profile special tests apply to, so panel-count completeness alone
     * decides it.
     *
     * @return array evaluate()'s array plus 'final_is_complete' (bool),
     *               'reason' (string|null), 'missing_details'
     *               (string|null) — the latter two only set when
     *               final_is_complete is false — and 'special_tests_eligible'
     *               (bool, true only when a profile is linked AND at least
     *               one special-test parameter is present)
     */
    public function evaluateFull(TestResult $testResult): array
    {
        $result = $this->evaluate($testResult);

        if (! $result['applicable'] || ! $result['is_complete']) {
            $result['final_is_complete'] = false;
            $result['reason'] = $this->resolveIncompleteReason($result);
            $result['missing_details'] = $this->missingDetailsForReason($testResult, $result, $result['reason']);
            $result['special_tests_eligible'] = false;

            return $result;
        }

        $matchedCount = 0;

        if ($result['test_result_profiles_count'] > 0) {
            $missingParams = $this->missingSpecialTestParameters($testResult);
            $matchedCount = self::SPECIAL_TEST_TOTAL_PARAMETERS - count($missingParams);

            if ($matchedCount > 0 && ! empty($missingParams)) {
                $result['final_is_complete'] = false;
                $result['reason'] = 'special_tests_missing_parameters';
                $result['missing_details'] = 'Missing parameters: '.implode(', ', $missingParams);
                $result['special_tests_eligible'] = true;

                return $result;
            }
        }

        $result['final_is_complete'] = true;
        $result['reason'] = null;
        $result['missing_details'] = null;
        $result['special_tests_eligible'] = $matchedCount > 0;

        return $result;
    }

    /**
     * The unified entry point for deciding a TestResult's completion state
     * from scratch, given whatever panel/profile data currently exists.
     * Innoquest delivers panels incrementally (not always with a PDF), so
     * this must be safe to call on every batch that writes new
     * test_result_items, regardless of whether this specific delivery
     * included a PDF.
     *
     * Order of checks (see evaluateFull()):
     * 1. evaluate() — ODB invoice cross-check + panel-count threshold. Not
     *    complete -> record incomplete (reason: invoice_mismatch or
     *    panel_count) and stop.
     * 2. If the record has no linked panel_profile, special tests aren't
     *    applicable (they're only meaningful for profiled packages) -> skip
     *    straight to finalizing.
     * 3. If it has a linked profile and at least one special-test parameter
     *    is already present (a partial match), all raw test_result_items
     *    inputs the 7 special-test formulas need must be present -> if not,
     *    record incomplete (reason: special_tests_missing_parameters) and
     *    stop. MyHealth is never called and no calculation is attempted in
     *    this branch. If zero parameters are present (e.g. an ALEMO
     *    profile), special tests simply don't apply -> skip straight to
     *    finalizing, same as step 2.
     * 4. Finalize: is_completed=true, is_reviewed=false, clear any
     *    incomplete_test_results row. Only after this is committed,
     *    fire-and-forget (outside the transaction, own try/catch) trigger
     *    the actual special-test calculation — but only when
     *    special_tests_eligible is true (a linked profile with at least one
     *    matched parameter); otherwise there's nothing to calculate, so the
     *    call is skipped. This is where MyHealth gets called when
     *    eligible; its failure can no longer affect completeness at this
     *    point, matching "MyHealth failure just continues".
     *
     * @return bool true if the record is now complete (whether it already
     *              was, or was just promoted), false if it's incomplete.
     */
    public function resolve(TestResult $testResult): bool
    {
        $result = $this->evaluateFull($testResult);

        if (! $result['final_is_complete']) {
            $this->recordIncomplete($testResult, $result, $result['reason'], $result['missing_details']);

            return false;
        }

        try {
            DB::beginTransaction();

            $testResult->is_completed = true;
            $testResult->is_reviewed = false;
            $testResult->save();

            IncompleteTestResult::where('test_result_id', $testResult->id)->delete();

            DB::commit();

            Log::info('PanelCompletenessService: panel and special-test completeness satisfied, is_completed promoted', [
                'test_result_id' => $testResult->id,
                'expected_panel_count' => $result['expected_panel_count'],
                'actual_panel_count' => $result['actual_panel_count'],
            ]);
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('PanelCompletenessService: failed to promote newly-complete test result', [
                'test_result_id' => $testResult->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        if (! $result['special_tests_eligible']) {
            Log::info('PanelCompletenessService: no special-test parameters matched, skipping calculation', [
                'test_result_id' => $testResult->id,
            ]);

            return true;
        }

        try {
            $this->testResultCompilerService->ensureSpecialTestsCalculated($testResult);
        } catch (Throwable $e) {
            Log::error('PanelCompletenessService: special test calculation failed after completion', [
                'test_result_id' => $testResult->id,
                'error' => $e->getMessage(),
            ]);
        }

        return true;
    }

    /**
     * Refresh reason/missing_details/expected_panel_count/actual_panel_count
     * on an existing incomplete_test_results row from a fresh evaluateFull()
     * of its TestResult — without touching is_completed, is_reviewed, or any
     * other completion state. For backfilling diagnostic detail onto rows
     * created before missing_details existed, or refreshing stale detail
     * after data has since changed. Does nothing (no write) if the
     * TestResult now evaluates as complete — there's nothing left to
     * describe as missing; use resolve() or undo() separately to actually
     * restore it in that case.
     *
     * @return array evaluateFull()'s result, so the caller can report status
     */
    public function refreshIncompleteDetails(TestResult $testResult): array
    {
        $result = $this->evaluateFull($testResult);

        if ($result['final_is_complete']) {
            return $result;
        }

        $incompleteRecord = IncompleteTestResult::where('test_result_id', $testResult->id)->first();

        if (! $incompleteRecord) {
            return $result;
        }

        try {
            DB::beginTransaction();

            $incompleteRecord->update([
                'expected_panel_count' => $result['expected_panel_count'],
                'actual_panel_count' => $result['actual_panel_count'],
                'reason' => $result['reason'],
                'missing_details' => $result['missing_details'],
            ]);

            DB::commit();

            Log::info('PanelCompletenessService: refreshed incomplete_test_results diagnostic details', [
                'test_result_id' => $testResult->id,
                'reason' => $result['reason'],
                'missing_details' => $result['missing_details'],
            ]);
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('PanelCompletenessService: failed to refresh incomplete_test_results diagnostic details', [
                'test_result_id' => $testResult->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $result;
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

            $aiReviewRestored = false;

            if ($incompleteRecord->ai_review_id) {
                $aiReviewRestored = (bool) AIReview::withTrashed()
                    ->where('id', $incompleteRecord->ai_review_id)
                    ->restore();
            }

            if ($incompleteRecord->was_reviewed && !$aiReviewRestored) {
                Log::warning('PanelCompletenessService: undo found was_reviewed=true but ai_reviews row could not be restored (missing/already hard-deleted), forcing is_reviewed=false', [
                    'test_result_id' => $testResult->id,
                    'incomplete_test_result_id' => $incompleteRecord->id,
                    'ai_review_id' => $incompleteRecord->ai_review_id,
                ]);
            }

            $testResult->is_completed = true;
            $testResult->is_reviewed = $incompleteRecord->was_reviewed && $aiReviewRestored;
            $testResult->save();

            $incompleteRecord->delete();

            DB::commit();

            Log::info('PanelCompletenessService: incomplete flag undone, test result restored to original state', [
                'test_result_id' => $testResult->id,
                'was_reviewed' => $incompleteRecord->was_reviewed,
                'ai_review_id' => $incompleteRecord->ai_review_id,
                'ai_review_restored' => $aiReviewRestored,
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
