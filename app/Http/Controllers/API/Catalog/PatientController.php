<?php

namespace App\Http\Controllers\API\Catalog;

use App\Models\Patient;
use App\Services\PatientProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class PatientController extends BaseCatalogController
{
    protected function model(): string
    {
        return Patient::class;
    }

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedIndex($request);
    }

    public function show($id): JsonResponse
    {
        return $this->findById($id);
    }

    public function byGender(Request $request, $gender): JsonResponse
    {
        return $this->paginatedIndex($request, fn ($q) => $q->where('gender', $gender));
    }

    public function byAge(Request $request, $age): JsonResponse
    {
        return $this->paginatedIndex($request, fn ($q) => $q->where('age', $age));
    }

    /**
     * GET /api/patients/by-icno/{icno}
     *
     * Full patient profile by IC number. See PatientProfileService::build()
     * for the response shape; the same payload is sent to the MyHealth lab
     * feed by SendToMyHealthFeed.
     */
    public function byIcNo(Request $request, PatientProfileService $patientProfileService, $icno): JsonResponse
    {
        Log::info('PatientController@byIcNo: starting lookup', ['icno' => $icno]);

        try {
            $profile = $patientProfileService->build((string) $icno);

            if (! $profile) {
                Log::warning('PatientController@byIcNo: patient not found', ['icno' => $icno]);

                return response()->json([
                    'success' => false,
                    'message' => 'Patient not found',
                ], 404);
            }

            Log::info('PatientController@byIcNo: completed', array_merge(['icno' => $icno], $profile['stats']));

            return response()->json([
                'success' => true,
                'data' => $profile['data'],
            ], 200);
        } catch (Throwable $e) {
            Log::error('PatientController@byIcNo: failed', [
                'icno' => $icno,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve patient data',
                'error' => 'Internal server error',
            ], 500);
        }
    }
}
