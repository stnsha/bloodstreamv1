<?php

namespace App\Http\Middleware;

use App\Http\Controllers\API\ConsultCall\ConsultCallAuthController;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class ConsultCallAuthMiddleware
{
    /**
     * Verify the consult-call JWT and attach claims to the request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            Log::warning('ConsultCallAuth middleware: missing Authorization bearer token');

            return response()->json([
                'success' => false,
                'message' => 'Authorization token is required.',
            ], 401);
        }

        try {
            $payload = JWTAuth::setToken($token)->getPayload();
        } catch (TokenExpiredException $e) {
            Log::warning('ConsultCallAuth middleware: token expired');

            return response()->json([
                'success' => false,
                'message' => 'Token has expired.',
            ], 401);
        } catch (TokenInvalidException $e) {
            Log::warning('ConsultCallAuth middleware: token invalid', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Token is invalid.',
            ], 401);
        } catch (JWTException $e) {
            Log::warning('ConsultCallAuth middleware: JWT error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Token error.',
            ], 401);
        }

        // Ensure this is a consult-call token, not an api.auth token
        if ($payload->get('token_type') !== ConsultCallAuthController::TOKEN_TYPE) {
            Log::warning('ConsultCallAuth middleware: wrong token type', [
                'token_type' => $payload->get('token_type'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid token type.',
            ], 401);
        }

        $request->attributes->set('staff_id', $payload->get('staff_id'));
        $request->attributes->set('staff_department_id', $payload->get('staff_department_id'));
        $request->attributes->set('consult_call_role', $payload->get('consult_call'));

        Log::info('ConsultCallAuth middleware: authenticated', [
            'staff_id' => $payload->get('staff_id'),
            'consult_call_role' => $payload->get('consult_call'),
        ]);

        return $next($request);
    }
}
