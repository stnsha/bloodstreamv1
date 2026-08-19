<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * Queue workers (ai-webhooks, panel, ai-reviews) run as separate
     * Windows Task Scheduler tasks via dedicated batch files.
     * This prevents foreground commands from blocking each other.
     *
     * Only periodic commands remain here.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Phase 2A: Find orphaned test results that missed AI review and re-dispatch them
        $schedule->command('ai:reconcile-reviews --hours=6 --limit=200')
            ->hourlyAt(5)
            ->environments(['production'])
            ->withoutOverlapping(30);

        // Phase 2B: Retry failed AI reviews from the ai_errors table
        $schedule->command('ai:retry-failed-reviews --hours=12 --limit=50')
            ->hourlyAt(20)
            ->environments(['production'])
            ->withoutOverlapping(30);

        // Phase 2C: Dispatch any unreviewed results to the AI server
        $schedule->command('ai:dispatch-unreviewed-async')
            ->everyFifteenMinutes()
            ->environments(['production'])
            ->withoutOverlapping(18);

        // Dynamic CSV export queue worker — processes jobs from the 'exports' queue, exits when empty
        $schedule->command('queue:work --queue=exports --stop-when-empty --timeout=3600 --tries=1')
            ->everyMinute()
            ->environments(['production'])
            ->withoutOverlapping(60);

        // Phase 2E: Keep panel_profiles_count in sync with panel_panel_profiles so
        // PanelCompletenessService has accurate expected-panel-count data
        $schedule->command('panels:sync-profile-counts')
            ->dailyAt('00:00')
            ->environments(['production'])
            ->withoutOverlapping(30);

        // Phase 2D/2G/2H merged: full panel-completeness reconciliation cycle —
        // revert stale is_completed=1 records (min-age-hours=1 default protects
        // in-flight deliveries), promote/refresh incomplete_test_results rows
        // (stamping manually_completed_at on promotion), and redispatch AI
        // review + consult-call re-eval for records that received new panel
        // data after their review finished.
        $schedule->command('panels:master-reconcile --limit=200 --force')
            ->hourlyAt(40)
            ->environments(['production'])
            ->withoutOverlapping(30);

        // Manual backfill only — do not schedule
        // php artisan testing:run-consult-eligibility --dry-run
        // php artisan testing:run-consult-eligibility --limit=100 --offset=0
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
