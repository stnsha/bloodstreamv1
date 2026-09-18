<?php

namespace App\Http\Controllers\API\Catalog;

use App\Models\AIReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class AIReviewController extends BaseCatalogController
{
    private const COMPLETED_STATUS = 'COMPLETED';

    protected function model(): string
    {
        return AIReview::class;
    }

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedIndex($request, fn ($q) => $q->where('processing_status', self::COMPLETED_STATUS));
    }

    public function show($id): JsonResponse
    {
        try {
            Log::info('AIReviewController@show: fetching record', ['id' => $id]);

            $record = AIReview::where('id', $id)
                ->where('processing_status', self::COMPLETED_STATUS)
                ->first();

            if (! $record) {
                Log::warning('AIReviewController@show: record not found', ['id' => $id]);

                return response()->json([
                    'success' => false,
                    'message' => 'Record not found',
                ], 404);
            }

            Log::info('AIReviewController@show: completed', ['id' => $id]);

            return response()->json(['success' => true, 'data' => $record], 200);
        } catch (Throwable $e) {
            Log::error('AIReviewController@show: failed', [
                'id' => $id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve record',
                'error' => 'Internal server error',
            ], 500);
        }
    }

    public function byTestResult(Request $request, $testResultId): JsonResponse
    {
        return $this->paginatedIndex($request, fn ($q) => $q
            ->where('test_result_id', $testResultId)
            ->where('processing_status', self::COMPLETED_STATUS));
    }
}
