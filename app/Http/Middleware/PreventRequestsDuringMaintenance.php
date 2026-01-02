<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance as Middleware;
use Illuminate\Support\Facades\Log;

class PreventRequestsDuringMaintenance extends Middleware
{
    /**
     * Maintenance window configuration.
     * Daily maintenance: 02:55 - 03:05 (Asia/Kuala_Lumpur timezone)
     */
    private const MAINTENANCE_START = '02:55';
    private const MAINTENANCE_END = '03:05';

    /**
     * The URIs that should be reachable while maintenance mode is enabled.
     *
     * @var array<int, string>
     */
    protected $except = [
        //
    ];

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // Check if this is an API request during maintenance window
        if ($this->isApiRoute($request) && $this->isMaintenanceWindow()) {
            return $this->maintenanceResponse($request);
        }

        // For non-API routes, use parent's standard maintenance mode check
        return parent::handle($request, $next);
    }

    /**
     * Determine if the request is targeting an API route.
     *
     * @param \Illuminate\Http\Request $request
     * @return bool
     */
    private function isApiRoute($request): bool
    {
        return str_starts_with($request->getPathInfo(), '/api/');
    }

    /**
     * Determine if current time is within maintenance window.
     * Uses timezone-safe comparison with app's configured timezone.
     *
     * @return bool
     */
    private function isMaintenanceWindow(): bool
    {
        $currentTime = now()->format('H:i');

        return $currentTime >= self::MAINTENANCE_START
            && $currentTime < self::MAINTENANCE_END;
    }

    /**
     * Return a maintenance mode response.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    private function maintenanceResponse($request)
    {
        Log::channel('auth')->warning('API request blocked: scheduled maintenance window active', [
            'path' => $request->getPathInfo(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'maintenance_window' => self::MAINTENANCE_START . ' - ' . self::MAINTENANCE_END,
            'current_time' => now()->format('Y-m-d H:i:s'),
        ]);

        return response()->json([
            'error' => 'Service Unavailable',
            'message' => 'The application is currently under scheduled maintenance. Please try again later.',
            'status' => 503,
        ], 503, [
            'Retry-After' => '660', // 11 minutes (10 min maintenance + 1 min buffer)
        ]);
    }
}
