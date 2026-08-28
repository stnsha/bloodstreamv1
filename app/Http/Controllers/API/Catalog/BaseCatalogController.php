<?php

namespace App\Http\Controllers\API\Catalog;

use App\Http\Controllers\Controller;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

abstract class BaseCatalogController extends Controller
{
    /**
     * Fully-qualified model class this controller lists/looks up.
     */
    abstract protected function model(): string;

    /**
     * Paginated listing, optionally narrowed by a filter scope (e.g. by
     * lab_id, gender, code). Shared by every Catalog controller's index()
     * and by-* filter methods.
     */
    protected function paginatedIndex(Request $request, ?Closure $scope = null): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 50);
        $perPage = max(1, min($perPage, 200));
        $logContext = static::class;

        try {
            $model = $this->model();
            $query = $model::query();

            if ($scope) {
                $scope($query);
            }

            Log::info("{$logContext}@index: fetching records", ['per_page' => $perPage]);

            $results = $query->orderByDesc('id')->paginate($perPage);

            Log::info("{$logContext}@index: completed", ['total' => $results->total()]);

            return response()->json([
                'success' => true,
                'data' => $results->items(),
                'meta' => [
                    'current_page' => $results->currentPage(),
                    'per_page' => $results->perPage(),
                    'total' => $results->total(),
                    'last_page' => $results->lastPage(),
                ],
            ], 200);
        } catch (Throwable $e) {
            Log::error("{$logContext}@index: failed", [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve records',
                'error' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Single record lookup by primary key. Shared by every Catalog
     * controller's show().
     */
    protected function findById($id): JsonResponse
    {
        $logContext = static::class;

        try {
            $model = $this->model();

            Log::info("{$logContext}@show: fetching record", ['id' => $id]);

            $record = $model::find($id);

            if (! $record) {
                Log::warning("{$logContext}@show: record not found", ['id' => $id]);

                return response()->json([
                    'success' => false,
                    'message' => 'Record not found',
                ], 404);
            }

            Log::info("{$logContext}@show: completed", ['id' => $id]);

            return response()->json(['success' => true, 'data' => $record], 200);
        } catch (Throwable $e) {
            Log::error("{$logContext}@show: failed", [
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
}
