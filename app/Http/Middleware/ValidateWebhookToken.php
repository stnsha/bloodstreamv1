<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ValidateWebhookToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expectedToken = config('credentials.webhook.ai_result_token');

        // Extract token from Authorization header
        $providedToken = $request->bearerToken();

        // Log authentication attempt
        Log::channel('webhook')->info('Webhook authentication attempt', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'has_token' => !empty($providedToken)
        ]);

        // Validate token exists in config
        if (empty($expectedToken)) {
            Log::channel('webhook')->error('Webhook token not configured in credentials.php');

            return response()->json([
                'success' => false,
                'message' => 'Webhook authentication not configured'
            ], 500);
        }

        // Validate token is provided
        if (empty($providedToken)) {
            Log::channel('webhook')->warning('Webhook authentication failed - missing token', [
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Missing authentication token'
            ], 401);
        }

        // Use constant-time comparison to prevent timing attacks
        if (!hash_equals($expectedToken, $providedToken)) {
            Log::channel('webhook')->warning('Webhook authentication failed - invalid token', [
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Invalid authentication token'
            ], 401);
        }

        // Token is valid
        Log::channel('webhook')->info('Webhook authenticated successfully', [
            'ip' => $request->ip()
        ]);

        return $next($request);
    }
}
