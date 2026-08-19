<?php

namespace App\Console\Commands;

use App\Models\IncompleteTestResult;
use App\Models\TestResult;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PanelsMasterReconcile extends Command
{
    protected $signature = 'panels:master-reconcile
                            {--from= : Start of the shared date range (Y-m-d), passed through to all three steps; each step falls back to its own default when omitted}
                            {--to=   : End of the shared date range (Y-m-d), passed through to all three steps}
                            {--limit= : Maximum number of records per step (omit to process all matched by each step)}
                            {--min-age-hours=1 : Step 1 only — skip reverting a record unless its test_results.updated_at is at least this many hours old, so a record still mid-delivery is never reverted out from under an in-progress batch}
                            {--dry-run : Preview all three steps without making any changes}
                            {--force : Skip the confirmation prompt (required for unattended/scheduled runs)}';

    protected $description = 'Run the full panel-completeness reconciliation cycle in one pass: (1) panels:recheck-incomplete — revert is_completed=1 records whose panels no longer meet the completeness threshold, respecting --min-age-hours so in-flight deliveries are left alone; (2) panels:reconcile-incomplete — promote incomplete_test_results rows that now meet the threshold, stamping manually_completed_at on each so it is never reverted again by step 1; (3) panels:recheck-late-reviews — redispatch AI review + consult-call re-eval for completed/reviewed records that received new panel data after their review finished.';

    public function handle(): int
    {
        $from = $this->option('from');
        $to = $this->option('to');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $minAgeHours = (int) $this->option('min-age-hours');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if ($minAgeHours < 0) {
            $this->error('--min-age-hours must be a non-negative integer.');

            return Command::FAILURE;
        }

        Log::channel('ai-command')->info('PanelsMasterReconcile started', [
            'from' => $from ?? 'default',
            'to' => $to ?? 'default',
            'limit' => $limit ?? 'all',
            'min_age_hours' => $minAgeHours,
            'dry_run' => $dryRun,
            'force' => $force,
        ]);

        if (! $dryRun && ! $force && ! $this->confirm('Run the full reconcile cycle (revert stale-complete, promote/refresh incomplete, redispatch late reviews)?')) {
            $this->info('Operation cancelled.');
            Log::channel('ai-command')->info('PanelsMasterReconcile: cancelled by user');

            return Command::SUCCESS;
        }

        $sharedOptions = array_filter([
            '--from' => $from,
            '--to' => $to,
            '--limit' => $limit,
        ], fn ($value) => $value !== null);

        $this->line('');
        $this->info('=== Step 1/3: panels:recheck-incomplete (revert stale-complete records) ===');

        $step1Exit = Artisan::call('panels:recheck-incomplete', $sharedOptions + [
            '--min-age-hours' => $minAgeHours,
            '--dry-run' => $dryRun,
            '--force' => true,
        ]);
        $this->line(Artisan::output());

        $this->line('');
        $this->info('=== Step 2/3: panels:reconcile-incomplete (promote/refresh incomplete records) ===');

        // Mirror panels:reconcile-incomplete's own default range (last 30 days)
        // so the before/after diff below matches exactly what that step processed.
        $reconcileFrom = Carbon::parse($from ?? now()->subDays(30)->toDateString())->startOfDay();
        $reconcileTo = Carbon::parse($to ?? now()->toDateString())->endOfDay();

        $beforeIncompleteIds = IncompleteTestResult::query()
            ->whereBetween('created_at', [$reconcileFrom, $reconcileTo])
            ->pluck('test_result_id')
            ->all();

        $step2Exit = Artisan::call('panels:reconcile-incomplete', $sharedOptions + [
            '--dry-run' => $dryRun,
            '--force' => true,
        ]);
        $this->line(Artisan::output());

        $stampedCount = 0;

        if (! $dryRun && ! empty($beforeIncompleteIds)) {
            $promotedIds = TestResult::whereIn('id', $beforeIncompleteIds)
                ->where('is_completed', true)
                ->whereNull('manually_completed_at')
                ->pluck('id')
                ->all();

            if (! empty($promotedIds)) {
                try {
                    DB::beginTransaction();

                    TestResult::whereIn('id', $promotedIds)->update([
                        'manually_completed_at' => now(),
                    ]);

                    DB::commit();

                    $stampedCount = count($promotedIds);

                    Log::channel('ai-command')->info('PanelsMasterReconcile: stamped manually_completed_at on promoted records', [
                        'test_result_ids' => $promotedIds,
                        'count' => $stampedCount,
                    ]);
                } catch (Throwable $e) {
                    DB::rollBack();

                    Log::channel('ai-command')->error('PanelsMasterReconcile: failed to stamp manually_completed_at on promoted records', [
                        'test_result_ids' => $promotedIds,
                        'error' => $e->getMessage(),
                    ]);

                    $this->error('Failed to stamp manually_completed_at on promoted records: '.$e->getMessage());
                }
            }
        }

        $this->info("Stamped manually_completed_at on {$stampedCount} promoted record(s).");

        $this->line('');
        $this->info('=== Step 3/3: panels:recheck-late-reviews (redispatch reviews for new panel data) ===');

        $step3Exit = Artisan::call('panels:recheck-late-reviews', $sharedOptions + [
            '--dry-run' => $dryRun,
            '--force' => true,
        ]);
        $this->line(Artisan::output());

        $this->line('');
        $this->info("Done. Step exit codes — recheck-incomplete: {$step1Exit}, reconcile-incomplete: {$step2Exit}, recheck-late-reviews: {$step3Exit}, manually_completed_at stamped: {$stampedCount}.");

        Log::channel('ai-command')->info('PanelsMasterReconcile completed', [
            'step1_exit' => $step1Exit,
            'step2_exit' => $step2Exit,
            'step3_exit' => $step3Exit,
            'manually_completed_at_stamped' => $stampedCount,
        ]);

        return ($step1Exit === Command::FAILURE && $step2Exit === Command::FAILURE && $step3Exit === Command::FAILURE)
            ? Command::FAILURE
            : Command::SUCCESS;
    }
}
