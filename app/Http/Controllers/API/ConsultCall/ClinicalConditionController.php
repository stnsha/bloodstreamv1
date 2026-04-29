<?php

namespace App\Http\Controllers\API\ConsultCall;

use App\Http\Controllers\Controller;
use App\Models\ClinicalCondition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ClinicalConditionController extends Controller
{
    public function index(): JsonResponse
    {
        Log::info('ClinicalCondition index: retrieving all active conditions');

        $conditions = ClinicalCondition::orderBy('id')->get();

        Log::info('ClinicalCondition index: completed', ['total' => $conditions->count()]);

        return response()->json([
            'success' => true,
            'data' => $conditions,
            'message' => 'Clinical conditions retrieved successfully.',
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        Log::info('ClinicalCondition update: starting', ['id' => $id]);

        $condition = ClinicalCondition::find($id);

        if (! $condition) {
            return response()->json([
                'success' => false,
                'message' => 'Clinical condition not found.',
            ], 404);
        }

        $validated = $request->validate([
            'description' => 'required|string|max:500',
            'risk_tier'   => 'required|integer|in:0,1,2,3',
        ]);

        try {
            DB::beginTransaction();

            $condition->update([
                'description' => $validated['description'],
                'risk_tier'   => $validated['risk_tier'],
            ]);

            DB::commit();

            ClinicalCondition::clearCache();

            Log::info('ClinicalCondition update: completed', ['id' => $id]);

            return response()->json([
                'success' => true,
                'data'    => $condition->fresh(),
                'message' => 'Clinical condition updated successfully.',
            ]);
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('ClinicalCondition update: failed', [
                'id'    => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update clinical condition.',
            ], 500);
        }
    }

    public function toggle(int $id): JsonResponse
    {
        Log::info('ClinicalCondition toggle: starting', ['id' => $id]);

        $condition = ClinicalCondition::find($id);

        if (! $condition) {
            return response()->json([
                'success' => false,
                'message' => 'Clinical condition not found.',
            ], 404);
        }

        try {
            DB::beginTransaction();

            $condition->update(['is_active' => ! $condition->is_active]);

            DB::commit();

            ClinicalCondition::clearCache();

            Log::info('ClinicalCondition toggle: completed', [
                'id'        => $id,
                'is_active' => $condition->is_active,
            ]);

            return response()->json([
                'success'   => true,
                'data'      => ['is_active' => $condition->is_active],
                'message'   => $condition->is_active ? 'Condition activated.' : 'Condition deactivated.',
            ]);
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('ClinicalCondition toggle: failed', [
                'id'    => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle clinical condition.',
            ], 500);
        }
    }
}
