<?php

namespace App\Http\Middleware;

use App\Models\LabCredential;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class APIAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken() ?? $request->cookie('jwt_token');

        if (!$token) {
            return response()->json(['error' => 'Unauthorized. Token is required.'], 401);
        }

        try {
            $user = Auth::guard('lab')->setToken($token)->user();
            Auth::guard('lab')->setUser($user);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        $labCredential = LabCredential::where('lab_id', $user->lab_id)->first();

        if ($labCredential && !in_array($labCredential->role, ['lab', 'admin'])) {
            return response()->json(['error' => 'Access restricted'], 403);
        }

        return $next($request);
    }
}
