<?php

namespace App\Http\Controllers\API\ConsultCall;

use App\Http\Controllers\Controller;
use App\Models\AddOn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class AddOnController extends Controller
{
    public function index(): JsonResponse
    {
        Log::info('AddOn index: retrieving active add-ons');

        $addOns = AddOn::where('is_active', true)->orderBy('name')->get();

        Log::info('AddOn index: completed', ['total' => $addOns->count()]);

        return response()->json([
            'success' => true,
            'data' => $addOns,
            'message' => 'Add-ons retrieved successfully.',
        ]);
    }

    public function all(): JsonResponse
    {
        Log::info('AddOn all: retrieving all add-ons');

        $addOns = AddOn::orderBy('name')->get();

        Log::info('AddOn all: completed', ['total' => $addOns->count()]);

        return response()->json([
            'success' => true,
            'data' => $addOns,
            'message' => 'Add-ons retrieved successfully.',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        Log::info('AddOn store: creating new add-on');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        try {
            DB::beginTransaction();

            $addOn = AddOn::create([
                'name' => $validated['name'],
                'is_active' => true,
            ]);

            DB::commit();

            Log::info('AddOn store: completed', ['id' => $addOn->id]);

            return response()->json([
                'success' => true,
                'data' => $addOn,
                'message' => 'Add-on created successfully.',
            ], 201);
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('AddOn store: failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create add-on.',
            ], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        Log::info('AddOn update: starting', ['id' => $id]);

        $addOn = AddOn::find($id);

        if (! $addOn) {
            return response()->json([
                'success' => false,
                'message' => 'Add-on not found.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        try {
            DB::beginTransaction();

            $addOn->update([
                'name' => $validated['name'],
            ]);

            DB::commit();

            Log::info('AddOn update: completed', ['id' => $id]);

            return response()->json([
                'success' => true,
                'data' => $addOn->fresh(),
                'message' => 'Add-on updated successfully.',
            ]);
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('AddOn update: failed', ['id' => $id, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update add-on.',
            ], 500);
        }
    }

    public function toggle(int $id): JsonResponse
    {
        Log::info('AddOn toggle: starting', ['id' => $id]);

        $addOn = AddOn::find($id);

        if (! $addOn) {
            return response()->json([
                'success' => false,
                'message' => 'Add-on not found.',
            ], 404);
        }

        try {
            DB::beginTransaction();

            $addOn->update(['is_active' => ! $addOn->is_active]);

            DB::commit();

            Log::info('AddOn toggle: completed', ['id' => $id, 'is_active' => $addOn->is_active]);

            return response()->json([
                'success' => true,
                'data' => ['is_active' => $addOn->is_active],
                'message' => $addOn->is_active ? 'Add-on activated.' : 'Add-on deactivated.',
            ]);
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('AddOn toggle: failed', ['id' => $id, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle add-on.',
            ], 500);
        }
    }
}
