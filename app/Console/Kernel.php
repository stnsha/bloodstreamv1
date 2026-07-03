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

        // Daily snapshot of incomplete_test_results (ref_id, lab_no) to CSV for operational review
        // $schedule->command('export:incomplete-test-results')
        //     ->dailyAt('08:00')
        //     ->environments(['production'])
        //     ->withoutOverlapping(30);

        // Phase 2E: Keep panel_profiles_count in sync with panel_panel_profiles so
        // PanelCompletenessService has accurate expected-panel-count data
        $schedule->command('panels:sync-profile-counts')
            ->dailyAt('00:00')
            ->environments(['production'])
            ->withoutOverlapping(30);

        // Phase 2D: Reconcile incomplete_test_results — promote records that now
        // resolve, refresh reason/missing_details for the rest
        $schedule->command('panels:reconcile-incomplete --limit=200 --force')
            ->hourlyAt(35)
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
