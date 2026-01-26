<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api/v1')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // High-volume lab result endpoints: 500/minute per user
        // Allows for batch processing of lab results (e.g., 450 concurrent requests)
        RateLimiter::for('lab-results', function (Request $request) {
            return Limit::perMinute(500)->by(
                optional($request->user())->id ?: $request->ip()
            );
        });

        // General API: 1000/minute per user (default for most endpoints)
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(1000)->by(
                optional($request->user())->id ?: $request->ip()
            );
        });

        // Authentication endpoints: 60/minute per IP (stricter to prevent brute force)
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });
    }
}
