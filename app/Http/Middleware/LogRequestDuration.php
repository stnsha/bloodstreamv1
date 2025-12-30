<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogRequestDuration
{
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);

        $response = $next($request);

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        if ($duration > 1000) {
            Log::channel('performance')->warning('Slow API request detected', [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'duration_ms' => $duration,
                'status_code' => $response->status(),
                'ip' => $request->ip(),
            ]);
        } else {
            Log::channel('performance')->info('API request completed', [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'duration_ms' => $duration,
                'status_code' => $response->status(),
            ]);
        }

        return $response;
    }
}
