<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //    if (config('app.env') === 'production') {
        //     	URL::forceScheme('https');
        // 	}

        DB::listen(function ($query) {
            if ($query->time > 100) { // time is in milliseconds
                Log::info('Slow Query Detected', [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'time_ms' => $query->time,
                ]);
            }
        });

        // Register migration processing rate limiter
        RateLimiter::for('migration-processing', function ($job) {
            // Rate limit per partition (0-9)
            return Limit::perMinute(10)
                ->by($job->itemId % 10);
        });
    }
}