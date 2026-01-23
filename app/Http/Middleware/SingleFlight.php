<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SingleFlight
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $lockKey = 'single-flight:' . $request->path();
        $lockTtl = 30; // seconds - covers longest /result/patient transactions
        $blockTimeout = 5; // seconds - wait before rejecting

        $lock = cache()->lock($lockKey, $lockTtl);
        $startWait = microtime(true);

        // Try to acquire lock, waiting up to $blockTimeout seconds
        $acquired = $lock->block($blockTimeout);

        if (! $acquired) {
            $waitedMs = round((microtime(true) - $startWait) * 1000, 2);

            Log::warning('SingleFlight: Request rejected after wait timeout', [
                'path' => $request->path(),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'waited_ms' => $waitedMs,
                'lock_key' => $lockKey,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Server busy processing another request. Please retry.',
                'error' => 'SINGLE_FLIGHT_TIMEOUT',
                'retry_after' => 10,
            ], 429)->header('Retry-After', 10);
        }

        $startProcess = microtime(true);

        Log::info('SingleFlight: Lock acquired', [
            'path' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'lock_key' => $lockKey,
        ]);

        try {
            return $next($request);
        } finally {
            $durationMs = round((microtime(true) - $startProcess) * 1000, 2);

            $lock->release();

            Log::info('SingleFlight: Lock released', [
                'path' => $request->path(),
                'duration_ms' => $durationMs,
                'lock_key' => $lockKey,
            ]);
        }
    }
}
