<?php

namespace App\Http\Controllers\API\General;

use App\Http\Controllers\Controller;
use App\Models\TestResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class TestResultController extends Controller
{
    /**
     * Restrict the query to the caller's lab, unless the caller is the
     * superadmin lab (id 1), matching LabResultsController::show().
     */
    private function scopeToLab($query)
    {
        $user = Auth::guard('lab')->user();
        $lab_id = $user->lab_id;

        if ($lab_id !== 1) {
            $query->whereHas('doctor', function ($q) use ($lab_id) {
                $q->where('lab_id', $lab_id);
            });
        }

        return $lab_id;
    }

    /**
     * GET /api/test-results
     * Lists TestResult rows (summary fields only, no nested panel/item
     * tree) visible to the caller's lab. Cursor-paginated so it stays
     * fast regardless of table size -- no COUNT(*) query, no OFFSET scan.
     * Use /api/test-results/search to fetch a specific id/lab_no/patient_id
     * with its TestResultItem rows.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 50);
        $perPage = max(1, min($perPage, 200));

        try {
            $query = TestResult::query()->select([
                'id', 'doctor_id', 'patient_id', 'lab_no', 'ref_id',
                'is_completed', 'is_reviewed',
                'collected_date', 'received_date', 'reported_date',
            ]);
            $lab_id = $this->scopeToLab($query);

            Log::info('TestResultController@index: fetching test results', [
                'lab_id' => $lab_id,
                'per_page' => $perPage,
            ]);

            $testResults = $query
                ->with([
                    'patient:id,icno,ic_type,name,age,gender',
                    'doctor:id,lab_id,name,code',
                ])
                ->orderByDesc('id')
                ->cursorPaginate($perPage);

            Log::info('TestResultController@index: completed', [
                'lab_id' => $lab_id,
                'count' => $testResults->count(),
            ]);

            return response()->json([
                'success' => true,
                'data' => $testResults->items(),
                'meta' => [
                    'per_page' => $testResults->perPage(),
                    'has_more' => $testResults->hasMorePages(),
                    'next_cursor' => optional($testResults->nextCursor())->encode(),
                    'prev_cursor' => optional($testResults->previousCursor())->encode(),
                ],
            ], 200);
        } catch (Throwable $e) {
            Log::error('TestResultController@index: failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve test results',
                'error' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * POST /api/test-results/search
     * Filters by id, lab_no, and/or patient_id (each scalar or array),
     * read from the JSON request body. Response includes patient, doctor,
     * testResultProfiles, testResultSpecialTests, and testResultItems
     * (with amendments and panelComments) -- no panel/panelItem/
     * panelProfile/panelCategory chain.
     * At least one of the three must be present; matching is a single
     * result or a bulk list depending on how many rows match.
     */
    public function search(Request $request): JsonResponse
    {
        if (! $request->filled('id') && ! $request->filled('lab_no') && ! $request->filled('patient_id')) {
            return response()->json([
                'success' => false,
                'message' => 'At least one of id, lab_no, or patient_id is required.',
            ], 422);
        }

        $request->validate([
            'id' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    foreach ((array) $value as $item) {
                        if (! is_numeric($item)) {
                            $fail('The id field must contain only integers.');

                            return;
                        }
                    }
                },
            ],
            'lab_no' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    foreach ((array) $value as $item) {
                        if (! is_string($item) && ! is_numeric($item)) {
                            $fail('The lab_no field must contain only strings.');

                            return;
                        }
                    }
                },
            ],
            'patient_id' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    foreach ((array) $value as $item) {
                        if (! is_numeric($item)) {
                            $fail('The patient_id field must contain only integers.');

                            return;
                        }
                    }
                },
            ],
        ]);

        $ids = $request->filled('id') ? array_map('intval', (array) $request->input('id')) : null;
        $labNos = $request->filled('lab_no') ? (array) $request->input('lab_no') : null;
        $patientIds = $request->filled('patient_id') ? array_map('intval', (array) $request->input('patient_id')) : null;

        try {
            $query = TestResult::query();
            $lab_id = $this->scopeToLab($query);

            Log::info('TestResultController@search: searching test results', [
                'lab_id' => $lab_id,
                'id' => $ids,
                'lab_no' => $labNos,
                'patient_id' => $patientIds,
            ]);

            if ($ids) {
                $query->whereIn('id', $ids);
            }
            if ($labNos) {
                $query->whereIn('lab_no', $labNos);
            }
            if ($patientIds) {
                $query->whereIn('patient_id', $patientIds);
            }

            $testResults = $query->with([
                'patient',
                'doctor',
                'testResultProfiles',
                'testResultSpecialTests',
                'testResultItems.amendments',
                'testResultItems.panelComments',
            ])->get();

            if ($testResults->isEmpty()) {
                Log::warning('TestResultController@search: no test results found', [
                    'lab_id' => $lab_id,
                    'id' => $ids,
                    'lab_no' => $labNos,
                    'patient_id' => $patientIds,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'No test results found for the given criteria',
                ], 404);
            }

            Log::info('TestResultController@search: completed', [
                'lab_id' => $lab_id,
                'count' => $testResults->count(),
            ]);

            return response()->json([
                'success' => true,
                'data' => $testResults,
                'count' => $testResults->count(),
            ], 200);
        } catch (Throwable $e) {
            Log::error('TestResultController@search: failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to search test results',
                'error' => 'Internal server error',
            ], 500);
        }
    }
}
