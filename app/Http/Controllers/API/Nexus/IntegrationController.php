<?php

namespace App\Http\Controllers\API\Nexus;

use App\Http\Controllers\Controller;
use App\Http\Controllers\API\Innoquest\PDFController;
use App\Models\Patient;
use App\Models\TestResult;
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
                    ->select(['id', 'lab_no', 'collected_date', 'is_completed', 'is_reviewed'])
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

            $labId = $testResult->doctor?->lab_id;

            if ($labId !== 2) {
                Log::warning('Nexus getResultById: unsupported lab', [
                    'test_result_id' => $id,
                    'lab_id'         => $labId,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'PDF export is not supported for this lab.',
                ], 422);
            }

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

}
