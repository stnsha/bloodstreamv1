<?php

namespace App\Http\Controllers\API\ConsultCall;

use App\Http\Controllers\Controller;
use App\Models\ActionMaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Options for the "Action" dropdown on a consult call detail. Same shape as the
 * add-ons lookup: active list for the form, full list + create/update/toggle for
 * the maintenance page. Options are deactivated rather than deleted.
 */
class ActionMasterController extends Controller
{
    public function index(): JsonResponse
    {
        Log::info('ActionMaster index: retrieving active actions');

        $actions = ActionMaster::where('is_active', true)->orderBy('name')->get();

        Log::info('ActionMaster index: completed', ['total' => $actions->count()]);

        return response()->json([
            'success' => true,
            'data' => $actions,
            'message' => 'Actions retrieved successfully.',
        ]);
    }

    public function all(): JsonResponse
    {
        Log::info('ActionMaster all: retrieving all actions');

        $actions = ActionMaster::orderBy('name')->get();

        Log::info('ActionMaster all: completed', ['total' => $actions->count()]);

        return response()->json([
            'success' => true,
            'data' => $actions,
            'message' => 'Actions retrieved successfully.',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        Log::info('ActionMaster store: creating new action');

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:action_master,name',
        ]);

        try {
            DB::beginTransaction();

            $action = ActionMaster::create([
                'name' => $validated['name'],
                'is_active' => true,
            ]);

            DB::commit();

            Log::info('ActionMaster store: completed', ['id' => $action->id]);

            return response()->json([
                'success' => true,
                'data' => $action,
                'message' => 'Action created successfully.',
            ], 201);
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('ActionMaster store: failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create action.',
            ], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        Log::info('ActionMaster update: starting', ['id' => $id]);

        $action = ActionMaster::find($id);

        if (! $action) {
            return response()->json([
                'success' => false,
                'message' => 'Action not found.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('action_master', 'name')->ignore($id)],
        ]);

        try {
            DB::beginTransaction();

            $action->update(['name' => $validated['name']]);

            DB::commit();

            Log::info('ActionMaster update: completed', ['id' => $id]);

            return response()->json([
                'success' => true,
                'data' => $action->fresh(),
                'message' => 'Action updated successfully.',
            ]);
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('ActionMaster update: failed', ['id' => $id, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update action.',
            ], 500);
        }
    }

    public function toggle(int $id): JsonResponse
    {
        Log::info('ActionMaster toggle: starting', ['id' => $id]);

        $action = ActionMaster::find($id);

        if (! $action) {
            return response()->json([
                'success' => false,
                'message' => 'Action not found.',
            ], 404);
        }

        try {
            DB::beginTransaction();

            $action->update(['is_active' => ! $action->is_active]);

            DB::commit();

            Log::info('ActionMaster toggle: completed', ['id' => $id, 'is_active' => $action->is_active]);

            return response()->json([
                'success' => true,
                'data' => ['is_active' => $action->is_active],
                'message' => $action->is_active ? 'Action activated.' : 'Action deactivated.',
            ]);
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('ActionMaster toggle: failed', ['id' => $id, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle action.',
            ], 500);
        }
    }
}
