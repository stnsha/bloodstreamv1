<?php

namespace App\Http\Controllers\API\Catalog;

use App\Models\Patient;
use App\Services\DoctorReviewMatcherService;
use App\Services\MyHealthMatcherService;
use App\Services\MyHealthService;
use App\Services\OctopusApiService;
use App\Services\TestResultHierarchyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class PatientController extends BaseCatalogController
{
    protected function model(): string
    {
        return Patient::class;
    }

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedIndex($request);
    }

    public function show($id): JsonResponse
    {
        return $this->findById($id);
    }

    public function byGender(Request $request, $gender): JsonResponse
    {
        return $this->paginatedIndex($request, fn ($q) => $q->where('gender', $gender));
    }

    public function byAge(Request $request, $age): JsonResponse
    {
        return $this->paginatedIndex($request, fn ($q) => $q->where('age', $age));
    }

    /**
     * GET /api/patients/by-icno/{icno}
     *
     * Full patient profile by IC number:
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
     */
    public function byIcNo(
        Request $request,
        MyHealthService $myHealthService,
        OctopusApiService $octopusApiService,
        DoctorReviewMatcherService $doctorReviewMatcher,
        MyHealthMatcherService $myHealthMatcher,
        TestResultHierarchyService $testResultHierarchy,
        $icno
    ): JsonResponse
    {
        Log::info('PatientController@byIcNo: starting lookup', ['icno' => $icno]);

        try {
            $patient = Patient::where('icno', $icno)->first();

            if (! $patient) {
                Log::warning('PatientController@byIcNo: patient not found', ['icno' => $icno]);

                return response()->json([
                    'success' => false,
                    'message' => 'Patient not found',
                ], 404);
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

            $myHealthRecords = $myHealthService->getAllCheckRecordsByIC($icno);
            $myHealthByTestResult = [];

            if ($myHealthRecords->isNotEmpty()) {
                $recordIds = $myHealthRecords->pluck('id')->all();
                $detailsByParameter = $myHealthService->getAllRecordDetailsBatch($recordIds);
                $myHealthByTestResult = $myHealthMatcher->matchByCollectedDate($testResults, $detailsByParameter);
            }

            try {
                $bloodTestSales = $octopusApiService->getBloodTestSalesByIcNo($icno);
            } catch (Throwable $e) {
                Log::error('PatientController@byIcNo: blood test sales lookup failed, continuing without it', [
                    'icno' => $icno,
                    'error' => $e->getMessage(),
                ]);

                $bloodTestSales = [];
            }

            $matchResult = $doctorReviewMatcher->match($testResults, $bloodTestSales);
            $matchedReviews = $matchResult['reviews'];
            $matchedSalesIds = $matchResult['matched_sales_ids'];

            $testResultsWithReview = $testResults->map(function ($testResult) use ($matchedReviews, $myHealthByTestResult, $testResultHierarchy) {
                $testResult->makeHidden(['testResultItems', 'testResultProfiles', 'profiles', 'aiReview']);

                return [
                    'test_result' => $testResult,
                    'review' => $matchedReviews[$testResult->id] ?? null,
                    'myhealth' => $myHealthByTestResult[$testResult->id] ?? [],
                    'test_result_items' => $testResultHierarchy->build($testResult),
                ];
            })->values();

            $unmatchedBloodTestSales = array_values(array_filter(
                $bloodTestSales,
                fn ($row) => ! in_array((int) $row['id'], $matchedSalesIds, true)
            ));

            Log::info('PatientController@byIcNo: completed', [
                'icno' => $icno,
                'patient_id' => $patient->id,
                'test_result_count' => $testResults->count(),
                'matched_review_count' => count(array_filter($matchedReviews)),
                'matched_sales_count' => count($matchedSalesIds),
                'unmatched_sales_count' => count($unmatchedBloodTestSales),
                'myhealth_record_count' => $myHealthRecords->count(),
                'blood_test_sales_count' => count($bloodTestSales),
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'patient' => $patient,
                    'blood_test_sales' => $unmatchedBloodTestSales,
                    'test_results' => $testResultsWithReview,
                ],
            ], 200);
        } catch (Throwable $e) {
            Log::error('PatientController@byIcNo: failed', [
                'icno' => $icno,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve patient data',
                'error' => 'Internal server error',
            ], 500);
        }
    }
}
