<?php

namespace App\Services;

use App\Models\Patient;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class PatientProfileService
{
    protected MyHealthService $myHealthService;

    protected OctopusApiService $octopusApiService;

    protected DoctorReviewMatcherService $doctorReviewMatcher;

    protected MyHealthMatcherService $myHealthMatcher;

    protected TestResultHierarchyService $testResultHierarchy;

    public function __construct(
        MyHealthService $myHealthService,
        OctopusApiService $octopusApiService,
        DoctorReviewMatcherService $doctorReviewMatcher,
        MyHealthMatcherService $myHealthMatcher,
        TestResultHierarchyService $testResultHierarchy
    ) {
        $this->myHealthService = $myHealthService;
        $this->octopusApiService = $octopusApiService;
        $this->doctorReviewMatcher = $doctorReviewMatcher;
        $this->myHealthMatcher = $myHealthMatcher;
        $this->testResultHierarchy = $testResultHierarchy;
    }

    /**
     * Full patient profile by IC number, shaped as the `data` object of
     * GET /api/patients/by-icno/{icno}:
     * - patient record
     * - every test result for the patient across all labs, with
     *   test_result_items formatted as Profile > Category > Panel > Panel
     *   Item, the same hierarchy used by the Innoquest PDF report
     * - MyHealth check_record readings per test result: every reading
     *   within 14 days of that test result's collected_date, grouped by
     *   parameter
     * - a single combined "review" per test result: blood_test_sales rows
     *   are fetched from the ODB blood test API by IC number and matched
     *   onto each test result (ref_id first, then a same month / 5-day date
     *   window); the ODB doc_review always wins when present, otherwise
     *   falling back to the test result's own ai_reviews row. Matched sales
     *   rows are removed from the top-level blood_test_sales list -- only
     *   rows that never matched any test result are returned there.
     *
     * When $collectedSince is given, only test results collected on or after
     * it are returned. Review matching still runs against every test result,
     * so a sales row belonging to an older result is not reported as
     * unmatched.
     *
     * Returns ['data' => <response data>, 'stats' => <counts for logging>],
     * or null when no patient has this IC number.
     */
    public function build(string $icno, ?Carbon $collectedSince = null): ?array
    {
        $patient = Patient::where('icno', $icno)->first();

        if (! $patient) {
            return null;
        }

        $testResults = $patient->testResults()
            ->with([
                'doctor',
                'testResultItems.panelComments.masterPanelComment',
                'testResultProfiles',
                'aiReview',
            ])
            ->orderByDesc('collected_date')
            ->get();

        $myHealthRecords = $this->myHealthService->getAllCheckRecordsByIC($icno);
        $myHealthByTestResult = [];

        if ($myHealthRecords->isNotEmpty()) {
            $recordIds = $myHealthRecords->pluck('id')->all();
            $detailsByParameter = $this->myHealthService->getAllRecordDetailsBatch($recordIds);
            $myHealthByTestResult = $this->myHealthMatcher->matchByCollectedDate($testResults, $detailsByParameter);
        }

        try {
            $bloodTestSales = $this->octopusApiService->getBloodTestSalesByIcNo($icno);
        } catch (Throwable $e) {
            Log::error('PatientProfileService: blood test sales lookup failed, continuing without it', [
                'icno' => $icno,
                'error' => $e->getMessage(),
            ]);

            $bloodTestSales = [];
        }

        $matchResult = $this->doctorReviewMatcher->match($testResults, $bloodTestSales);
        $matchedReviews = $matchResult['reviews'];
        $matchedSalesIds = $matchResult['matched_sales_ids'];

        $selectedTestResults = $collectedSince
            ? $testResults->filter(fn ($testResult) => $testResult->collected_date
                && Carbon::parse($testResult->collected_date)->gte($collectedSince))
            : $testResults;

        $testResultsWithReview = $selectedTestResults->map(function ($testResult) use ($matchedReviews, $myHealthByTestResult) {
            $testResult->makeHidden(['testResultItems', 'testResultProfiles', 'profiles', 'aiReview']);

            return [
                'test_result' => $testResult,
                'review' => $matchedReviews[$testResult->id] ?? null,
                'myhealth' => $myHealthByTestResult[$testResult->id] ?? [],
                'test_result_items' => $this->testResultHierarchy->build($testResult),
            ];
        })->values();

        $unmatchedBloodTestSales = array_values(array_filter(
            $bloodTestSales,
            fn ($row) => ! in_array((int) $row['id'], $matchedSalesIds, true)
        ));

        return [
            'data' => [
                'patient' => $patient,
                'blood_test_sales' => $unmatchedBloodTestSales,
                'test_results' => $testResultsWithReview,
            ],
            'stats' => [
                'patient_id' => $patient->id,
                'test_result_count' => $testResultsWithReview->count(),
                'matched_review_count' => count(array_filter($matchedReviews)),
                'matched_sales_count' => count($matchedSalesIds),
                'unmatched_sales_count' => count($unmatchedBloodTestSales),
                'myhealth_record_count' => $myHealthRecords->count(),
                'blood_test_sales_count' => count($bloodTestSales),
            ],
        ];
    }
}
