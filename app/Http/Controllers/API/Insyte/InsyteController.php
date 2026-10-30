<?php

namespace App\Http\Controllers\API\Insyte;

use App\Http\Controllers\Controller;
use App\Services\InsyteService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class InsyteController extends Controller
{
    protected InsyteService $insyteService;

    public function __construct(InsyteService $insyteService)
    {
        $this->insyteService = $insyteService;
    }

    /**
     * POST /api/v1/insyte/updateCompletionByTestResultId
     * Body: { "test_result_id": 123, "http_status": 200 }
     *
     * Called by Insyte (MyHealth lab feed server) with how it processed a test
     * result we sent. See InsyteService for what each http_status does.
     */
    public function updateCompletionByTestResultId(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'test_result_id' => 'required|integer|min:1',
            'http_status' => 'required|integer|between:100,599',
        ]);

        if ($validator->fails()) {
            Log::warning('InsyteController@updateCompletionByTestResultId: validation failed', [
                'errors' => $validator->errors()->toArray(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $testResultId = (int) $request->input('test_result_id');
        $httpStatus = (int) $request->input('http_status');

        try {
            Log::info('InsyteController@updateCompletionByTestResultId: received', [
                'test_result_id' => $testResultId,
                'http_status' => $httpStatus,
            ]);

            $result = $this->insyteService->handleCallback($testResultId, $httpStatus);

            $message = match (true) {
                $result['ignored'] => 'Insyte testing period: delivery recorded, is_reviewed not changed',
                $result['outcome'] === InsyteService::OUTCOME_COMPLETED => 'Test result marked as completed',
                $result['outcome'] === InsyteService::OUTCOME_PROCESSING => 'Processing acknowledged, awaiting final status',
                $result['outcome'] === InsyteService::OUTCOME_REJECTED => 'Rejection recorded, will not be resent automatically',
                default => 'Failure recorded, will be resent',
            };

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $result,
            ], 200);
        } catch (ModelNotFoundException $e) {
            Log::warning('InsyteController@updateCompletionByTestResultId: test result not found', [
                'test_result_id' => $testResultId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Test result not found',
            ], 404);
        } catch (Throwable $e) {
            Log::error('InsyteController@updateCompletionByTestResultId: failed', [
                'test_result_id' => $testResultId,
                'http_status' => $httpStatus,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update test result',
                'error' => 'Internal server error',
            ], 500);
        }
    }
}
