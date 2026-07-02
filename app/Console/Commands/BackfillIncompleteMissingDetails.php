<?php

namespace App\Console\Commands;

use App\Models\IncompleteTestResult;
use App\Services\PanelCompletenessService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class BackfillIncompleteMissingDetails extends Command
{
    protected $signature = 'panels:backfill-missing-details
                            {--from= : Start of incomplete_test_results created_at range (Y-m-d), omit for no lower bound}
                            {--to=   : End of incomplete_test_results created_at range (Y-m-d), omit for no upper bound}
                            {--limit= : Maximum number of records to process (omit to process all)}
                            {--offset= : Number of matched records to skip before applying --limit, for paging through a large range in batches}
                            {--dry-run : Preview affected records without making any changes}
                            {--force : Skip the confirmation prompt (required for unattended/scheduled runs)}';

    protected $description = 'Backfill/refresh reason and missing_details on existing incomplete_test_results rows by re-evaluating their TestResult with PanelCompletenessService::refreshIncompleteDetails. Does not change is_completed/is_reviewed or any other completion state — a record that now evaluates as complete is left untouched (use panels:recheck-ai-eligibility or panels:revert-incomplete to actually restore it).';

    public function handle(PanelCompletenessService $panelCompletenessService): int
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

        Log::channel('ai-command')->info('BackfillIncompleteMissingDetails started', [
            'from' => $fromDate?->toDateTimeString() ?? 'unbounded',
            'to' => $toDate?->toDateTimeString() ?? 'unbounded',
            'limit' => $limit ?? 'all',
            'offset' => $offset ?? 0,
            'dry_run' => $dryRun,
            'force' => $force,
        ]);

        $this->info('Querying incomplete_test_results to backfill...');

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
            Log::channel('ai-command')->info('BackfillIncompleteMissingDetails: no records found', [
                'from' => $fromDate?->toDateString() ?? 'unbounded',
                'to' => $toDate?->toDateString() ?? 'unbounded',
                'offset' => $offset ?? 0,
            ]);

            return Command::SUCCESS;
        }

        $this->info("Total matched: {$totalMatched} record(s). Will check: {$count}".($offset ? " (offset by --offset={$offset})" : '').($limit ? " (limited by --limit={$limit})" : '').'.');
        $this->line('');

        $previewRows = [];
        $refreshableCount = 0;

        foreach ($incompleteRecords as $record) {
            $testResult = $record->testResult;

            if (! $testResult) {
                $previewRows[] = [$record->test_result_id, 'N/A', 'N/A', 'TEST RESULT NOT FOUND', '-'];

                continue;
            }

            $result = $panelCompletenessService->evaluateFull($testResult);

            if ($result['final_is_complete']) {
                $previewRows[] = [$testResult->id, $testResult->lab_no ?? 'N/A', 'NOW COMPLETE', 'not touched — nothing missing to describe', '-'];

                continue;
            }

            $refreshableCount++;

            $previewRows[] = [
                $testResult->id,
                $testResult->lab_no ?? 'N/A',
                'WILL REFRESH',
                $result['reason'],
                $result['missing_details'] ?? '-',
            ];
        }

        // Always show preview table
        $this->table(
            ['Test Result ID', 'Lab No', 'Status', 'Reason', 'Missing Details'],
            $previewRows
        );

        $this->info("{$refreshableCount} record(s) will be refreshed out of {$count} matched.");

        if ($dryRun) {
            $this->line('');
            $this->info('DRY RUN — no changes made.');
            Log::channel('ai-command')->info('BackfillIncompleteMissingDetails: dry run completed', [
                'matched_count' => $count,
                'refreshable_count' => $refreshableCount,
            ]);

            return Command::SUCCESS;
        }

        if ($refreshableCount === 0) {
            Log::channel('ai-command')->info('BackfillIncompleteMissingDetails: nothing refreshable', [
                'matched_count' => $count,
            ]);

            return Command::SUCCESS;
        }

        $this->line('');
        if (! $force && ! $this->confirm("Refresh reason/missing_details for {$refreshableCount} record(s)? is_completed/is_reviewed are never touched by this command.")) {
            $this->info('Operation cancelled.');
            Log::channel('ai-command')->info('BackfillIncompleteMissingDetails: cancelled by user');

            return Command::SUCCESS;
        }

        $startTime = microtime(true);
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

            Log::channel('ai-command')->info('BackfillIncompleteMissingDetails: processing record', [
                'test_result_id' => $testResult->id,
            ]);

            try {
                $result = $panelCompletenessService->refreshIncompleteDetails($testResult);

                if ($result['final_is_complete']) {
                    $skipped++;
                    $resultRows[] = [$testResult->id, 'SKIPPED', 'Now evaluates as complete'];
                } else {
                    $refreshed++;
                    $resultRows[] = [$testResult->id, 'REFRESHED', '-'];
                }

                Log::channel('ai-command')->info('BackfillIncompleteMissingDetails: record completed', [
                    'test_result_id' => $testResult->id,
                    'final_is_complete' => $result['final_is_complete'],
                ]);
            } catch (Throwable $e) {
                $failed++;
                $resultRows[] = [$testResult->id, 'FAILED', $e->getMessage()];

                Log::channel('ai-command')->error('BackfillIncompleteMissingDetails: record failed', [
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
        $this->info("Done. Refreshed: {$refreshed}, Skipped: {$skipped}, Failed: {$failed}, Duration: {$duration}s.");

        Log::channel('ai-command')->info('BackfillIncompleteMissingDetails completed', [
            'matched_count' => $count,
            'refreshed' => $refreshed,
            'skipped' => $skipped,
            'failed' => $failed,
            'duration_seconds' => $duration,
        ]);

        return $failed > 0 && $refreshed === 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
