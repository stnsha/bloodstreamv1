<?php

namespace App\Console\Commands;

use App\Models\IncompleteTestResult;
use App\Services\PanelCompletenessService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class RevertIncompletePanels extends Command
{
    protected $signature = 'panels:revert-incomplete
                            {--from= : Start of incomplete_test_results created_at range (Y-m-d), defaults to 30 days ago}
                            {--to=   : End of incomplete_test_results created_at range (Y-m-d), defaults to today}
                            {--limit= : Maximum number of records to process (omit to process all)}
                            {--dry-run : Preview affected records without making any changes}';

    protected $description = 'Undo a previous panels:recheck-incomplete revert: restore is_completed, is_reviewed, and the soft-deleted ai_reviews row to their original state, and remove the incomplete_test_results row. Does not re-check current panel data.';

    public function handle(PanelCompletenessService $panelCompletenessService): int
    {
        $from = $this->option('from') ?? now()->subDays(30)->toDateString();
        $to = $this->option('to') ?? now()->toDateString();
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $dryRun = $this->option('dry-run');

        $fromDate = Carbon::parse($from)->startOfDay();
        $toDate = Carbon::parse($to)->endOfDay();

        Log::channel('ai-command')->info('RevertIncompletePanels started', [
            'from' => $fromDate->toDateTimeString(),
            'to' => $toDate->toDateTimeString(),
            'limit' => $limit ?? 'all',
            'dry_run' => $dryRun,
        ]);

        $this->info('Querying incomplete_test_results to revert...');

        $query = IncompleteTestResult::whereBetween('created_at', [$fromDate, $toDate])
            ->where(function ($q) {
                $q->whereNull('reason')->orWhere('reason', 'panel_count');
            })
            ->with(['testResult.patient:id,icno']);

        $totalMatched = $query->count();

        $incompleteRecords = $query
            ->orderBy('created_at', 'desc')
            ->when($limit, fn ($q) => $q->limit($limit))
            ->get();

        $count = $incompleteRecords->count();

        if ($count === 0) {
            $this->info('No incomplete_test_results found in range.');
            Log::channel('ai-command')->info('RevertIncompletePanels: no records found', [
                'from' => $fromDate->toDateString(),
                'to' => $toDate->toDateString(),
            ]);

            return Command::SUCCESS;
        }

        $this->info("Total matched: {$totalMatched} record(s). Will revert: {$count}".($limit ? " (limited by --limit={$limit})" : '').'.');
        $this->line('');

        $previewRows = [];
        $revertibleRecords = [];

        foreach ($incompleteRecords as $record) {
            $testResult = $record->testResult;

            if (! $testResult) {
                $previewRows[] = [$record->test_result_id, 'N/A', 'N/A', 'N/A', $record->was_reviewed ? 'Yes' : 'No', $record->created_at->format('Y-m-d H:i'), 'TEST RESULT NOT FOUND'];

                continue;
            }

            $revertibleRecords[] = ['record' => $record, 'testResult' => $testResult];

            $previewRows[] = [
                $testResult->id,
                $testResult->lab_no ?? 'N/A',
                $testResult->collected_date ? $testResult->collected_date->format('Y-m-d') : 'N/A',
                $testResult->patient->icno ?? 'N/A',
                $record->was_reviewed ? 'Yes' : 'No',
                $record->created_at->format('Y-m-d H:i'),
                'WILL REVERT',
            ];
        }

        // Always show preview table
        $this->table(
            ['Test Result ID', 'Lab No', 'Collected Date', 'Patient IC', 'Was Reviewed', 'Flagged At', 'Status'],
            $previewRows
        );

        $revertibleCount = count($revertibleRecords);
        $this->info("{$revertibleCount} record(s) will be reverted out of {$count} matched.");

        if ($dryRun) {
            $this->line('');
            $this->info('DRY RUN — no changes made.');
            Log::channel('ai-command')->info('RevertIncompletePanels: dry run completed', [
                'matched_count' => $count,
                'revertible_count' => $revertibleCount,
            ]);

            return Command::SUCCESS;
        }

        if ($revertibleCount === 0) {
            Log::channel('ai-command')->info('RevertIncompletePanels: nothing revertible', [
                'matched_count' => $count,
            ]);

            return Command::SUCCESS;
        }

        $this->line('');
        if (! $this->confirm("Proceed with reverting {$revertibleCount} record(s) back to their original state? This restores is_completed, is_reviewed, and any soft-deleted AI review, and removes them from incomplete_test_results.")) {
            $this->info('Operation cancelled.');
            Log::channel('ai-command')->info('RevertIncompletePanels: cancelled by user');

            return Command::SUCCESS;
        }

        $startTime = microtime(true);
        $reverted = 0;
        $failed = 0;
        $resultRows = [];

        foreach ($revertibleRecords as $entry) {
            $testResult = $entry['testResult'];

            Log::channel('ai-command')->info('RevertIncompletePanels: processing record', [
                'test_result_id' => $testResult->id,
            ]);

            try {
                $wasUndone = $panelCompletenessService->undo($testResult);

                if ($wasUndone) {
                    $reverted++;
                    $resultRows[] = [$testResult->id, 'REVERTED', '-'];
                } else {
                    $resultRows[] = [$testResult->id, 'SKIPPED', 'Nothing to undo'];
                }

                Log::channel('ai-command')->info('RevertIncompletePanels: record completed', [
                    'test_result_id' => $testResult->id,
                    'reverted' => $wasUndone,
                ]);
            } catch (Throwable $e) {
                $failed++;
                $resultRows[] = [$testResult->id, 'FAILED', $e->getMessage()];

                Log::channel('ai-command')->error('RevertIncompletePanels: record failed', [
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
        $this->info("Done. Reverted: {$reverted}, Failed: {$failed}, Duration: {$duration}s.");

        Log::channel('ai-command')->info('RevertIncompletePanels completed', [
            'matched_count' => $count,
            'reverted' => $reverted,
            'failed' => $failed,
            'duration_seconds' => $duration,
        ]);

        return $failed > 0 && $reverted === 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
