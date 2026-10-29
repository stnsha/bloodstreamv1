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
     * GET /api/insyte/updateCompletionByTestResultId?test_result_id={id}
     * Called by Insyte once a test result has been received. Sets
     * test_results.is_reviewed = 1. Idempotent.
     */
    public function updateCompletionByTestResultId(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'test_result_id' => 'required|integer|min:1',
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

        $testResultId = (int) $request->query('test_result_id');

        try {
            Log::info('InsyteController@updateCompletionByTestResultId: received', ['test_result_id' => $testResultId]);

            $result = $this->insyteService->markReviewed($testResultId);

            return response()->json([
                'success' => true,
                'message' => $result['already_reviewed']
                    ? 'Test result already marked as reviewed'
                    : 'Test result marked as reviewed',
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
