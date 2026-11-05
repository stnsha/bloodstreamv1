<?php

namespace App\Http\Controllers\API\Nexus;

use App\Http\Controllers\Controller;
use App\Http\Controllers\API\Innoquest\PDFController;
use App\Models\Patient;
use App\Models\TestResult;
use App\Services\Nexus\NexusPatientHistoryService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class IntegrationController extends Controller
{
    public function getResultByICNo(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'icno'   => 'required|array|min:1',
            'icno.*' => 'required|string',
        ]);

        $icNumbers = $validated['icno'];

        Log::info('Nexus getResultByICNo start', ['ic_count' => count($icNumbers)]);

        try {
            $data = [];

            foreach ($icNumbers as $icno) {
                $patient = Patient::where('icno', $icno)->first();

                if ($patient === null) {
                    $data[$icno] = [];
                    continue;
                }

                $results = $patient->testResults()
                    ->select(['id', 'lab_no', 'ref_id', 'collected_date', 'is_completed', 'is_reviewed'])
                    ->orderBy('collected_date', 'desc')
                    ->get();

                $data[$icno] = $results->map(function ($result) {
                    if ($result->is_completed) {
                        $status = 'Completed';
                    } else {
                        $status = 'Pending';
                    }

                    return [
                        'test_result_id' => $result->id,
                        'ref_id'         => $result->ref_id,
                        'collected_date' => $result->collected_date
                            ? $result->collected_date->format('dmY')
                            : null,
                        'lab_no'         => $result->lab_no,
                        'status'         => $status,
                    ];
                })->values()->all();
            }

            Log::info('Nexus getResultByICNo complete', ['ic_count' => count($icNumbers)]);

            return response()->json([
                'success' => true,
                'data'    => $data,
            ]);
        } catch (Throwable $e) {
            Log::error('Nexus getResultByICNo failed', [
                'error'    => $e->getMessage(),
                'ic_count' => count($icNumbers),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve results',
            ], 500);
        }
    }

    public function getResultById(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'test_result_id' => 'required|integer|exists:test_results,id',
        ]);

        $id = $validated['test_result_id'];

        Log::info('Nexus getResultById start', ['test_result_id' => $id]);

        try {
            $testResult = TestResult::with('doctor')->find($id);

            if ($testResult === null) {
                Log::warning('Nexus getResultById: test result not found', ['test_result_id' => $id]);

                return response()->json([
                    'success' => false,
                    'message' => 'Test result not found.',
                ], 404);
            }

            // Nexus may export results from every lab; no lab restriction is applied.
            $labId = $testResult->doctor?->lab_id;

            Log::info('Nexus getResultById: delegating to PDFController', [
                'test_result_id' => $id,
                'lab_id'         => $labId,
            ]);

            return app(PDFController::class)->exportByTestResultIdForNexus($id);
        } catch (Throwable $e) {
            Log::error('Nexus getResultById failed', [
                'test_result_id' => $id,
                'error'          => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve result.',
            ], 500);
        }
    }

    /**
     * Full test result history of one patient across every lab, with the raw
     * lab data of each result in the ai_reviews.compiled_results format.
     */
    public function getHistoryByICNo(Request $request, NexusPatientHistoryService $historyService): JsonResponse
    {
        $validated = $request->validate([
            'icno' => 'required|string|max:50',
            'from' => 'nullable|date_format:Y-m-d',
            'to'   => array_filter([
                'nullable',
                'date_format:Y-m-d',
                $request->filled('from') ? 'after_or_equal:from' : null,
            ]),
        ]);

        $icno = $validated['icno'];
        // Missing, null or empty from / to means no date limit: all results are returned.
        $from = filled($validated['from'] ?? null) ? Carbon::createFromFormat('Y-m-d', $validated['from']) : null;
        $to   = filled($validated['to'] ?? null) ? Carbon::createFromFormat('Y-m-d', $validated['to']) : null;

        Log::info('Nexus getHistoryByICNo start', [
            'icno' => $icno,
            'from' => $validated['from'] ?? null,
            'to'   => $validated['to'] ?? null,
        ]);

        try {
            $history = $historyService->build($icno, $from, $to);

            if ($history === null) {
                Log::warning('Nexus getHistoryByICNo: patient not found', ['icno' => $icno]);

                return response()->json([
                    'success' => false,
                    'message' => 'Patient not found.',
                ], 404);
            }

            Log::info('Nexus getHistoryByICNo complete', ['icno' => $icno] + $history['stats']);

            return response()->json([
                'success' => true,
                'data'    => $history['data'],
            ]);
        } catch (Throwable $e) {
            Log::error('Nexus getHistoryByICNo failed', [
                'icno'  => $icno,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve patient history.',
            ], 500);
        }
    }

}
