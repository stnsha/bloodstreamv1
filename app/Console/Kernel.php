<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $logPath = storage_path('logs/scheduler.log');

        // Phase 1: Process all queued jobs (panel results, AI webhooks, AI reviews, migrations)
        $schedule->command('queue:work database --queue=panel,ai-webhooks,ai-reviews,migration --timeout=300 --max-jobs=50 --max-time=600 --tries=3')
            ->everyFiveMinutes()
            ->environments(['production'])
            ->withoutOverlapping(15)
            ->runInBackground()
            ->appendOutputTo($logPath);

        // Phase 2A: Find orphaned test results that missed AI review and re-dispatch them
        $schedule->command('ai:reconcile-reviews --hours=6 --limit=200')
            ->hourlyAt(5)
            ->environments(['production'])
            ->withoutOverlapping(30)
            ->appendOutputTo($logPath);

        // Phase 2B: Retry failed AI reviews from the ai_errors table
        $schedule->command('ai:retry-failed-reviews --hours=12 --limit=50')
            ->hourlyAt(15)
            ->environments(['production'])
            ->withoutOverlapping(30)
            ->appendOutputTo($logPath);

        // Phase 2C: Dispatch any unreviewed results to the AI server
        $schedule->command('ai:dispatch-unreviewed-async')
            ->everyFifteenMinutes()
            ->environments(['production'])
            ->withoutOverlapping(10)
            ->appendOutputTo($logPath);
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
