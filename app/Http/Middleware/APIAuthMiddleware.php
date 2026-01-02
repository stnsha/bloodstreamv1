<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class APIAuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        Log::channel('auth')->info('RAW AUTH HEADER', [
            'authorization' => $request->header('Authorization'),
            'bearer_token'  => $request->bearerToken(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'endpoint' => $request->fullUrl(),
            'method' => $request->method(),
        ]);

        $logContext = [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'endpoint' => $request->fullUrl(),
            'method' => $request->method(),
        ];

        try {
            // Get token from request
            $token = $request->bearerToken() ?? $request->cookie('jwt_token');
            $tokenSource = $request->bearerToken() ? 'bearer' : ($request->cookie('jwt_token') ? 'cookie' : 'none');

            if (!$token) {
                Log::channel('auth')->warning('Authentication failed: No token provided', $logContext);
                return response()->json(['error' => 'Unauthorized. Token is required.'], 401);
            }

            // Log token metadata (never log full token for security)
            $tokenPreview = strlen($token) > 20
                ? substr($token, 0, 10) . '...' . substr($token, -10)
                : 'token_too_short';

            Log::channel('auth')->info('Authentication attempt', array_merge($logContext, [
                'token_source' => $tokenSource,
                'token_length' => strlen($token),
                'token_preview' => $tokenPreview,
            ]));

            // Authenticate using the lab guard with the token
            $user = Auth::guard('lab')->setToken($token)->user();

            if (!$user) {
                Log::channel('auth')->error('Authentication failed: User not found after token validation', array_merge($logContext, [
                    'token_preview' => $tokenPreview,
                    'reason' => 'User returned null from guard',
                ]));
                return response()->json(['error' => 'Invalid token'], 401);
            }

            // Log successful authentication
            Log::channel('auth')->info('Authentication successful', array_merge($logContext, [
                'lab_credential_id' => $user->id,
                'lab_id' => $user->lab_id,
                'username' => $user->username,
            ]));

            // The user is already authenticated by the guard, no need to call setUser again
            // Just proceed to the next middleware/controller
            return $next($request);
        } catch (TokenExpiredException $e) {
            Log::channel('auth')->error('Authentication failed: Token expired', array_merge($logContext, [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]));
            return response()->json(['error' => 'Token has expired'], 401);
        } catch (TokenInvalidException $e) {
            Log::channel('auth')->error('Authentication failed: Token invalid', array_merge($logContext, [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]));
            return response()->json(['error' => 'Token is invalid'], 401);
        } catch (JWTException $e) {
            Log::channel('auth')->error('Authentication failed: JWT exception', array_merge($logContext, [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]));
            return response()->json(['error' => 'Token error'], 401);
        } catch (Exception $e) {
            Log::channel('auth')->error('Authentication failed: General exception', array_merge($logContext, [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]));
            return response()->json(['error' => 'Authentication failed'], 401);
        }
    }
}