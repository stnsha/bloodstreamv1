<?php

namespace App\Console\Commands;

use App\Models\IncompleteTestResult;
use App\Services\PanelCompletenessService;
use App\Services\TestResultCompletionDispatcher;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class PromoteResolvedIncompletePanels extends Command
{
    protected $signature = 'panels:promote-resolved-incomplete
                            {--from= : Start of incomplete_test_results created_at range (Y-m-d), omit for no lower bound}
                            {--to=   : End of incomplete_test_results created_at range (Y-m-d), omit for no upper bound}
                            {--limit= : Maximum number of records to process (omit to process all)}
                            {--offset= : Number of matched records to skip before applying --limit, for paging through a large range in batches}
                            {--dry-run : Preview affected records without making any changes}
                            {--force : Skip the confirmation prompt (required for unattended/scheduled runs)}';

    protected $description = 'Live re-check every incomplete_test_results row (any reason) against PanelCompletenessService::evaluateFull, and promote (resolve + dispatch consult-call/AI) any that now evaluate as complete. Unlike panels:backfill-missing-details, this DOES change is_completed/is_reviewed for rows that are now resolvable — e.g. after an upstream data/logic fix (such as an ODB invoice-count correction) resolves a previously-genuine invoice_mismatch or special_tests_missing_parameters flag.';

    public function handle(PanelCompletenessService $panelCompletenessService, TestResultCompletionDispatcher $dispatcher): int
    {
        $from = $this->option('from');
        $to = $this->option('to');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $offset = $this->option('offset') ? (int) $this->option('offset') : null;

        if ($offset && ! $limit) {
            $this->error('--offset requires --limit to be set (MySQL does not support OFFSET without LIMIT).');

            return Command::FAILURE;
        }

        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $fromDate = $from ? Carbon::parse($from)->startOfDay() : null;
        $toDate = $to ? Carbon::parse($to)->endOfDay() : null;

        Log::channel('ai-command')->info('PromoteResolvedIncompletePanels started', [
            'from' => $fromDate?->toDateTimeString() ?? 'unbounded',
            'to' => $toDate?->toDateTimeString() ?? 'unbounded',
            'limit' => $limit ?? 'all',
            'offset' => $offset ?? 0,
            'dry_run' => $dryRun,
            'force' => $force,
        ]);

        $this->info('Querying incomplete_test_results to re-check...');

        $query = IncompleteTestResult::query()
            ->when($fromDate && $toDate, fn ($q) => $q->whereBetween('created_at', [$fromDate, $toDate]))
            ->when($fromDate && ! $toDate, fn ($q) => $q->where('created_at', '>=', $fromDate))
            ->when(! $fromDate && $toDate, fn ($q) => $q->where('created_at', '<=', $toDate))
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
            Log::channel('ai-command')->info('PromoteResolvedIncompletePanels: no records found', [
                'from' => $fromDate?->toDateString() ?? 'unbounded',
                'to' => $toDate?->toDateString() ?? 'unbounded',
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
                $previewRows[] = [$testResult->id, $testResult->lab_no ?? 'N/A', $record->reason ?? '-', 'WILL PROMOTE'];
            } else {
                $previewRows[] = [$testResult->id, $testResult->lab_no ?? 'N/A', $record->reason ?? '-', 'still '.$result['reason']];
            }
        }

        // Always show preview table
        $this->table(
            ['Test Result ID', 'Lab No', 'Stored Reason', 'Status'],
            $previewRows
        );

        $this->info("{$promotableCount} record(s) will be promoted out of {$count} matched.");

        if ($dryRun) {
            $this->line('');
            $this->info('DRY RUN — no changes made.');
            Log::channel('ai-command')->info('PromoteResolvedIncompletePanels: dry run completed', [
                'matched_count' => $count,
                'promotable_count' => $promotableCount,
            ]);

            return Command::SUCCESS;
        }

        if ($promotableCount === 0) {
            Log::channel('ai-command')->info('PromoteResolvedIncompletePanels: nothing promotable', [
                'matched_count' => $count,
            ]);

            return Command::SUCCESS;
        }

        $this->line('');
        if (! $force && ! $this->confirm("Promote {$promotableCount} record(s) to is_completed=1 and dispatch consult-call/AI review for each?")) {
            $this->info('Operation cancelled.');
            Log::channel('ai-command')->info('PromoteResolvedIncompletePanels: cancelled by user');

            return Command::SUCCESS;
        }

        $startTime = microtime(true);
        $promoted = 0;
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

            Log::channel('ai-command')->info('PromoteResolvedIncompletePanels: processing record', [
                'test_result_id' => $testResult->id,
            ]);

            try {
                $isComplete = $panelCompletenessService->resolve($testResult);

                if ($isComplete) {
                    $promoted++;
                    $resultRows[] = [$testResult->id, 'PROMOTED', '-'];
                    $dispatcher->dispatch($testResult);
                } else {
                    $skipped++;
                    $resultRows[] = [$testResult->id, 'SKIPPED', 'Still incomplete'];
                }

                Log::channel('ai-command')->info('PromoteResolvedIncompletePanels: record completed', [
                    'test_result_id' => $testResult->id,
                    'is_complete' => $isComplete,
                ]);
            } catch (Throwable $e) {
                $failed++;
                $resultRows[] = [$testResult->id, 'FAILED', $e->getMessage()];

                Log::channel('ai-command')->error('PromoteResolvedIncompletePanels: record failed', [
                    'test_result_id' => $testResult->id,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }
        }

        $duration = round(microtime(true) - $startTime, 2);

        $this->line('');
        $this->table(['Test Result ID', 'Status', 'Error'], $resultRows);
        $this->line('');
        $this->info("Done. Promoted: {$promoted}, Skipped: {$skipped}, Failed: {$failed}, Duration: {$duration}s.");

        Log::channel('ai-command')->info('PromoteResolvedIncompletePanels completed', [
            'matched_count' => $count,
            'promoted' => $promoted,
            'skipped' => $skipped,
            'failed' => $failed,
            'duration_seconds' => $duration,
        ]);

        return $failed > 0 && $promoted === 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
