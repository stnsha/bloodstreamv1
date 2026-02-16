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

        // Phase 1A: Process AI webhook jobs every minute for fast turnaround
        // Background workers use callbacks instead of appendOutputTo to avoid Windows file locks
        $schedule->command('queue:work database --queue=ai-webhooks --timeout=300 --max-jobs=50 --max-time=55 --tries=3')
            ->everyMinute()
            ->environments(['production'])
            ->withoutOverlapping(2)
            ->runInBackground()
            ->before($this->logEntry($logPath, 'queue:work ai-webhooks', 'STARTING'))
            ->onSuccess($this->logEntry($logPath, 'queue:work ai-webhooks', 'DONE'))
            ->onFailure($this->logEntry($logPath, 'queue:work ai-webhooks', 'FAIL'));

        // Phase 1B: Process remaining queued jobs (panel results, AI reviews)
        $schedule->command('queue:work database --queue=panel,ai-reviews --timeout=300 --max-jobs=50 --max-time=600 --tries=3')
            ->everyFiveMinutes()
            ->environments(['production'])
            ->withoutOverlapping(15)
            ->runInBackground()
            ->before($this->logEntry($logPath, 'queue:work panel,ai-reviews', 'STARTING'))
            ->onSuccess($this->logEntry($logPath, 'queue:work panel,ai-reviews', 'DONE'))
            ->onFailure($this->logEntry($logPath, 'queue:work panel,ai-reviews', 'FAIL'));

        // Phase 2A: Find orphaned test results that missed AI review and re-dispatch them
        // Foreground commands use appendOutputTo for full output capture
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
            ->everyTenMinutes()
            ->environments(['production'])
            ->withoutOverlapping(10)
            ->appendOutputTo($logPath);
    }

    /**
     * Create a closure that writes a log entry to a file.
     * Uses file_put_contents (open-write-close) to avoid persistent Windows file locks.
     */
    protected function logEntry(string $logPath, string $command, string $status): \Closure
    {
        return function () use ($logPath, $command, $status) {
            $timestamp = date('Y-m-d H:i:s');
            file_put_contents($logPath, "[{$timestamp}] {$command} -- {$status}\n", FILE_APPEND | LOCK_EX);
        };
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
