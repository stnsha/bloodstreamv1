<?php

namespace App\Http\Controllers\API\ConsultCall;

use App\Http\Controllers\Controller;
use App\Models\AddOn;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

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
}
