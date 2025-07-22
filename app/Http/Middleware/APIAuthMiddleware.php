<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class APIAuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        try {
            // Get token from request
            $token = $request->bearerToken() ?? $request->cookie('jwt_token');

            if (!$token) {
                return response()->json(['error' => 'Unauthorized. Token is required.'], 401);
            }

            // Authenticate using the lab guard with the token
            $user = Auth::guard('lab')->setToken($token)->user();

            if (!$user) {
                return response()->json(['error' => 'Invalid token'], 401);
            }

            // The user is already authenticated by the guard, no need to call setUser again
            // Just proceed to the next middleware/controller
            return $next($request);
        } catch (TokenExpiredException $e) {
            return response()->json(['error' => 'Token has expired'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['error' => 'Token is invalid'], 401);
        } catch (JWTException $e) {
            return response()->json(['error' => 'Token error'], 401);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Authentication failed'], 401);
        }
    }
}
