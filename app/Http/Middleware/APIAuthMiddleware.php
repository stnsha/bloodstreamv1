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
        Log::info('APIAuthMiddleware: Starting authentication check');
        Log::info('Authorization header: ' . $request->header('Authorization'));

        $user = Auth::guard('lab')->user();
        Log::info('Authenticated user: ' . ($user ? $user->id : 'null'));

        if (!$user) {
            Log::warning('APIAuthMiddleware: No authenticated user found');
            return response()->json(['error' => 'Unauthorized. Token is required.'], 401);
        }

        $labCredential = LabCredential::where('lab_id', $user->lab_id)->first();

        if ($labCredential && !in_array($labCredential->role, ['lab', 'admin'])) {
            return response()->json(['error' => 'Access restricted'], 403);
        }
        return $next($request);
    }
}
