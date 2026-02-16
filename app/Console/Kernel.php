<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * All commands run as FOREGROUND on Windows.
     * runInBackground() with start /b cmd silently fails on Windows,
     * causing queue workers to never process jobs.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Phase 1A: Process AI webhook jobs -- runs for 55 seconds, restarts every minute
        $schedule->command('queue:work database --queue=ai-webhooks --timeout=300 --max-jobs=50 --max-time=55 --tries=3')
            ->everyMinute()
            ->environments(['production'])
            ->withoutOverlapping(2);

        // Phase 1B: Process remaining queued jobs (panel results, AI reviews)
        // Runs for 55 seconds, restarts every minute
        $schedule->command('queue:work database --queue=panel,ai-reviews --timeout=300 --max-jobs=50 --max-time=55 --tries=3')
            ->everyMinute()
            ->environments(['production'])
            ->withoutOverlapping(2);

        // Phase 2A: Find orphaned test results that missed AI review and re-dispatch them
        $schedule->command('ai:reconcile-reviews --hours=6 --limit=200')
            ->hourlyAt(5)
            ->environments(['production'])
            ->withoutOverlapping(30);

        // Phase 2B: Retry failed AI reviews from the ai_errors table
        $schedule->command('ai:retry-failed-reviews --hours=12 --limit=50')
            ->hourlyAt(15)
            ->environments(['production'])
            ->withoutOverlapping(30);

        // Phase 2C: Dispatch any unreviewed results to the AI server
        $schedule->command('ai:dispatch-unreviewed-async')
            ->everyThirtyMinutes()
            ->environments(['production'])
            ->withoutOverlapping(35);
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
