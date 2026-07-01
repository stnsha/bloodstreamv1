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

    protected $description = 'Recheck records in incomplete_test_results; if all expected panels have since arrived, restore is_completed=true and remove them from incomplete_test_results';

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

        $this->info('Querying incomplete_test_results to recheck...');

        $query = IncompleteTestResult::whereBetween('created_at', [$fromDate, $toDate])
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

        $this->info("Total matched: {$totalMatched} record(s). Will check: {$count}" . ($limit ? " (limited by --limit={$limit})" : '') . '.');
        $this->line('');

        $previewRows = [];
        $resolvableCount = 0;
        $testResultsById = [];

        foreach ($incompleteRecords as $record) {
            $testResult = $record->testResult;

            if (! $testResult) {
                $previewRows[] = [$record->test_result_id, 'N/A', 'N/A', 'N/A', $record->expected_panel_count, $record->actual_panel_count, 'TEST RESULT NOT FOUND'];
                continue;
            }

            $testResultsById[$testResult->id] = $testResult;
            $result = $panelCompletenessService->evaluate($testResult);

            if ($result['is_complete']) {
                $resolvableCount++;
            }

            $previewRows[] = [
                $testResult->id,
                $testResult->lab_no ?? 'N/A',
                $testResult->collected_date ? $testResult->collected_date->format('Y-m-d') : 'N/A',
                $testResult->patient->icno ?? 'N/A',
                $result['expected_panel_count'],
                $result['actual_panel_count'],
                $result['is_complete'] ? 'NOW COMPLETE' : 'STILL INCOMPLETE',
            ];
        }

        // Always show preview table
        $this->table(
            ['Test Result ID', 'Lab No', 'Collected Date', 'Patient IC', 'Expected Panels', 'Actual Panels', 'Status'],
            $previewRows
        );

        $this->info("Found {$resolvableCount} record(s) now complete out of {$count} checked.");

        if ($dryRun) {
            $this->line('');
            $this->info('DRY RUN — no changes made.');
            Log::channel('ai-command')->info('RevertIncompletePanels: dry run completed', [
                'checked_count' => $count,
                'resolvable_count' => $resolvableCount,
            ]);

            return Command::SUCCESS;
        }

        if ($resolvableCount === 0) {
            Log::channel('ai-command')->info('RevertIncompletePanels: no records ready to resolve', [
                'checked_count' => $count,
            ]);

            return Command::SUCCESS;
        }

        $this->line('');
        if (!$this->confirm("Proceed with resolving {$resolvableCount} record(s)? This will set is_completed=true and remove them from incomplete_test_results.")) {
            $this->info('Operation cancelled.');
            Log::channel('ai-command')->info('RevertIncompletePanels: cancelled by user');

            return Command::SUCCESS;
        }

        $startTime = microtime(true);
        $resolved = 0;
        $failed = 0;
        $resultRows = [];

        foreach ($testResultsById as $testResult) {
            Log::channel('ai-command')->info('RevertIncompletePanels: processing record', [
                'test_result_id' => $testResult->id,
            ]);

            try {
                $wasResolved = $panelCompletenessService->resolve($testResult);

                if ($wasResolved) {
                    $resolved++;
                    $resultRows[] = [$testResult->id, 'RESOLVED', '-'];
                } else {
                    $resultRows[] = [$testResult->id, 'STILL INCOMPLETE', '-'];
                }

                Log::channel('ai-command')->info('RevertIncompletePanels: record completed', [
                    'test_result_id' => $testResult->id,
                    'resolved' => $wasResolved,
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
        $this->info("Done. Resolved: {$resolved}, Failed: {$failed}, Duration: {$duration}s.");

        if ($resolved > 0) {
            $this->info('Resolved records are now eligible for AI dispatch via ai:dispatch-unreviewed-async.');
        }

        Log::channel('ai-command')->info('RevertIncompletePanels completed', [
            'checked_count' => $count,
            'resolved' => $resolved,
            'failed' => $failed,
            'duration_seconds' => $duration,
        ]);

        return $failed > 0 && $resolved === 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
