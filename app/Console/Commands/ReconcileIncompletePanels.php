<?php

namespace App\Console\Commands;

use App\Models\IncompleteTestResult;
use App\Services\PanelCompletenessService;
use App\Services\TestResultCompilerService;
use App\Services\TestResultCompletionDispatcher;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReconcileIncompletePanels extends Command
{
    protected $signature = 'panels:reconcile-incomplete
                            {--from= : Start of incomplete_test_results created_at range (Y-m-d), defaults to 30 days ago}
                            {--to=   : End of incomplete_test_results created_at range (Y-m-d), defaults to today}
                            {--limit= : Maximum number of records to process (omit to process all)}
                            {--offset= : Number of matched records to skip before applying --limit, for paging through a large range in batches}
                            {--dry-run : Preview affected records without making any changes}
                            {--force : Skip the confirmation prompt (required for unattended/scheduled runs)}';

    protected $description = 'Live re-check every incomplete_test_results row against PanelCompletenessService::evaluateFull: promote (restore is_completed, recalculate special tests, dispatch consult-call/AI review) any that now evaluate as complete, or refresh reason/missing_details for any that are still incomplete.';

    public function handle(
        PanelCompletenessService $panelCompletenessService,
        TestResultCompilerService $testResultCompilerService,
        TestResultCompletionDispatcher $dispatcher
    ): int {
        $from = $this->option('from') ?? now()->subDays(30)->toDateString();
        $to = $this->option('to') ?? now()->toDateString();
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $offset = $this->option('offset') ? (int) $this->option('offset') : null;

        if ($offset && ! $limit) {
            $this->error('--offset requires --limit to be set (MySQL does not support OFFSET without LIMIT).');

            return Command::FAILURE;
        }

        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $fromDate = Carbon::parse($from)->startOfDay();
        $toDate = Carbon::parse($to)->endOfDay();

        Log::channel('ai-command')->info('ReconcileIncompletePanels started', [
            'from' => $fromDate->toDateTimeString(),
            'to' => $toDate->toDateTimeString(),
            'limit' => $limit ?? 'all',
            'offset' => $offset ?? 0,
            'dry_run' => $dryRun,
            'force' => $force,
        ]);

        $this->info('Querying incomplete_test_results to reconcile...');

        $query = IncompleteTestResult::query()
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->whereHas('testResult', fn ($q) => $q->where('is_completed', false))
            ->with(['testResult.patient:id,icno']);

        $totalMatched = (clone $query)->count();

        $incompleteRecords = $query
            ->orderBy('created_at', 'desc')
            ->when($offset, fn ($q) => $q->skip($offset))
            ->when($limit, fn ($q) => $q->limit($limit))
            ->get();

        $count = $incompleteRecords->count();

        if ($count === 0) {
            $this->info('No incomplete_test_results found in range.');
            Log::channel('ai-command')->info('ReconcileIncompletePanels: no records found', [
                'from' => $fromDate->toDateString(),
                'to' => $toDate->toDateString(),
                'offset' => $offset ?? 0,
            ]);

            return Command::SUCCESS;
        }

        $this->info("Total matched: {$totalMatched} record(s). Will check: {$count}".($offset ? " (offset by --offset={$offset})" : '').($limit ? " (limited by --limit={$limit})" : '').'.');
        $this->line('');

        $previewRows = [];
        $promotableCount = 0;

        foreach ($incompleteRecords as $record) {
            $testResult = $record->testResult;

            if (! $testResult) {
                $previewRows[] = [$record->test_result_id, 'N/A', $record->reason ?? '-', 'TEST RESULT NOT FOUND'];

                continue;
            }

            $result = $panelCompletenessService->evaluateFull($testResult);

            if ($result['final_is_complete']) {
                $promotableCount++;
                $previewRows[] = [$testResult->id, $testResult->lab_no ?? 'N/A', $record->reason ?? '-', 'PROMOTE'];
            } else {
                $previewRows[] = [$testResult->id, $testResult->lab_no ?? 'N/A', $record->reason ?? '-', 'REFRESH (still '.$result['reason'].')'];
            }
        }

        // Always show preview table
        $this->table(
            ['Test Result ID', 'Lab No', 'Stored Reason', 'Verdict'],
            $previewRows
        );

        $this->info("{$promotableCount} record(s) will be promoted, ".($count - $promotableCount)." will be refreshed, out of {$count} matched.");

        if ($dryRun) {
            $this->line('');
            $this->info('DRY RUN — no changes made.');
            Log::channel('ai-command')->info('ReconcileIncompletePanels: dry run completed', [
                'matched_count' => $count,
                'promotable_count' => $promotableCount,
            ]);

            return Command::SUCCESS;
        }

        $this->line('');
        if (! $force && ! $this->confirm("Reconcile {$count} record(s)? Resolved records will be promoted to is_completed=1 and dispatched for consult-call/AI review; the rest will have their reason/missing_details refreshed.")) {
            $this->info('Operation cancelled.');
            Log::channel('ai-command')->info('ReconcileIncompletePanels: cancelled by user');

            return Command::SUCCESS;
        }

        $startTime = microtime(true);
        $promoted = 0;
        $refreshed = 0;
        $skipped = 0;
        $failed = 0;
        $resultRows = [];

        foreach ($incompleteRecords as $record) {
            $testResult = $record->testResult;

            if (! $testResult) {
                $skipped++;
                $resultRows[] = [$record->test_result_id, 'SKIPPED', 'Test result not found'];

                continue;
            }

            Log::channel('ai-command')->info('ReconcileIncompletePanels: processing record', [
                'test_result_id' => $testResult->id,
            ]);

            try {
                $result = $panelCompletenessService->refreshIncompleteDetails($testResult);

                if ($result['final_is_complete']) {
                    $wasPromoted = $panelCompletenessService->undo($testResult);

                    if ($wasPromoted) {
                        $testResultCompilerService->ensureSpecialTestsCalculated($testResult);
                        $dispatcher->dispatch($testResult);
                        $promoted++;
                        $resultRows[] = [$testResult->id, 'PROMOTED', '-'];
                    } else {
                        $skipped++;
                        $resultRows[] = [$testResult->id, 'SKIPPED', 'Nothing to undo'];
                    }
                } else {
                    $refreshed++;
                    $resultRows[] = [$testResult->id, 'REFRESHED', $result['reason']];
                }

                Log::channel('ai-command')->info('ReconcileIncompletePanels: record completed', [
                    'test_result_id' => $testResult->id,
                    'final_is_complete' => $result['final_is_complete'],
                ]);
            } catch (Throwable $e) {
                $failed++;
                $resultRows[] = [$testResult->id, 'FAILED', $e->getMessage()];

                Log::channel('ai-command')->error('ReconcileIncompletePanels: record failed', [
                    'test_result_id' => $testResult->id,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }
        }

        $duration = round(microtime(true) - $startTime, 2);

        $this->line('');
        $this->table(['Test Result ID', 'Status', 'Detail'], $resultRows);
        $this->line('');
        $this->info("Done. Promoted: {$promoted}, Refreshed: {$refreshed}, Skipped: {$skipped}, Failed: {$failed}, Duration: {$duration}s.");

        Log::channel('ai-command')->info('ReconcileIncompletePanels completed', [
            'matched_count' => $count,
            'promoted' => $promoted,
            'refreshed' => $refreshed,
            'skipped' => $skipped,
            'failed' => $failed,
            'duration_seconds' => $duration,
        ]);

        return $failed > 0 && $promoted === 0 && $refreshed === 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
