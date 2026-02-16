<?php

namespace App\Http\Controllers\API\ConsultCall;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Facades\JWTFactory;

class ConsultCallAuthController extends Controller
{
    /**
     * Token type claim used to distinguish consult-call JWTs from api.auth JWTs.
     */
    public const TOKEN_TYPE = 'consult_call';

    /**
     * TTL in minutes (24 hours).
     */
    private const TTL_MINUTES = 1440;

    /**
     * Authenticate ODB staff and generate a consult-call JWT.
     *
     * Accepts staff_id, staff_department_id, and consult_call role from ODB proxy.
     * Department 16 is forced to super admin (consult_call = 1).
     */
    public function auth(Request $request): JsonResponse
    {
        Log::info('ConsultCallAuth: authentication attempt', [
            'staff_id' => $request->input('staff_id'),
            'staff_department_id' => $request->input('staff_department_id'),
        ]);

        try {
            $validated = $request->validate([
                'staff_id' => 'required|integer',
                'staff_department_id' => 'required|integer',
                'consult_call' => 'required|integer|in:0,1,2,3,4,5',
            ]);
        } catch (ValidationException $e) {
            Log::warning('ConsultCallAuth: validation failed', [
                'errors' => $e->errors(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        }

        // Department 16 = super admin override
        $consultCallRole = $validated['staff_department_id'] == 16
            ? 1
            : $validated['consult_call'];

        try {
            $payload = JWTFactory::customClaims([
                'sub' => $validated['staff_id'],
                'token_type' => self::TOKEN_TYPE,
                'staff_id' => $validated['staff_id'],
                'staff_department_id' => $validated['staff_department_id'],
                'consult_call' => $consultCallRole,
            ])->setTTL(self::TTL_MINUTES)->make();

            $token = JWTAuth::encode($payload)->get();
        } catch (JWTException $e) {
            Log::error('ConsultCallAuth: token generation failed', [
                'error' => $e->getMessage(),
                'staff_id' => $validated['staff_id'],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate token.',
            ], 500);
        }

        Log::info('ConsultCallAuth: token issued', [
            'staff_id' => $validated['staff_id'],
            'consult_call' => $consultCallRole,
        ]);

        return response()->json([
            'token' => $token,
            'expires_in' => self::TTL_MINUTES * 60,
        ]);
    }

    /**
     * Verify a consult-call JWT and return its payload.
     */
    public function verifyToken(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'token' => 'required|string',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'valid' => false,
                'message' => 'The token field is required.',
            ], 422);
        }

        try {
            $payload = JWTAuth::setToken($validated['token'])->getPayload();

            if ($payload->get('token_type') !== self::TOKEN_TYPE) {
                return response()->json([
                    'valid' => false,
                    'message' => 'Token is not a consult-call token.',
                ], 401);
            }

            return response()->json([
                'valid' => true,
                'message' => 'Token is valid',
                'payload' => $payload->toArray(),
            ]);
        } catch (TokenExpiredException $e) {
            return response()->json([
                'valid' => false,
                'message' => 'Token has expired',
            ], 401);
        } catch (TokenInvalidException $e) {
            return response()->json([
                'valid' => false,
                'message' => 'Token is invalid',
            ], 401);
        } catch (JWTException $e) {
            return response()->json([
                'valid' => false,
                'message' => 'Token is invalid or expired',
            ], 401);
        }
    }
}
