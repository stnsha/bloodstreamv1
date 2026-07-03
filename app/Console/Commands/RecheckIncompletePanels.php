<?php

namespace App\Console\Commands;

use App\Models\TestResult;
use App\Services\PanelCompletenessService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecheckIncompletePanels extends Command
{
    protected $signature = 'panels:recheck-incomplete
                            {--from= : Start of collected_date range (Y-m-d), defaults to 30 days ago}
                            {--to=   : End of collected_date range (Y-m-d), defaults to today}
                            {--limit= : Maximum number of records to process (omit to process all)}
                            {--offset= : Number of matched records to skip before applying --limit, for paging through a large range in batches}
                            {--dry-run : Preview affected records without making any changes}
                            {--force : Skip the confirmation prompt (required for unattended/scheduled runs)}
                            {--prioritize-ref-id : Process records with a non-null ref_id before those without, for higher-fidelity ODB-based completeness checks}';

    protected $description = 'Recheck test results marked is_completed=true against their expected panel profiles, reverting is_completed to false and recording any with missing panels in incomplete_test_results';

    public function handle(PanelCompletenessService $panelCompletenessService): int
    {
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
        $prioritizeRefId = $this->option('prioritize-ref-id');

        $fromDate = Carbon::parse($from)->startOfDay();
        $toDate = Carbon::parse($to)->endOfDay();

        Log::channel('ai-command')->info('RecheckIncompletePanels started', [
            'from' => $fromDate->toDateTimeString(),
            'to' => $toDate->toDateTimeString(),
            'limit' => $limit ?? 'all',
            'offset' => $offset ?? 0,
            'dry_run' => $dryRun,
            'force' => $force,
            'prioritize_ref_id' => $prioritizeRefId,
        ]);

        $this->info('Querying test results to recheck...');

        $query = TestResult::whereBetween('collected_date', [$fromDate, $toDate])
            ->where('is_completed', true);

        $totalMatched = $query->count();

        $testResults = $query
            ->with(['patient:id,icno', 'testResultProfiles'])
            ->when($prioritizeRefId, fn ($q) => $q->orderByRaw('ref_id IS NULL'))
            ->orderBy('collected_date', 'desc')
            ->when($offset, fn ($q) => $q->skip($offset))
            ->when($limit, fn ($q) => $q->limit($limit))
            ->get();

        $count = $testResults->count();

        if ($count === 0) {
            $this->info('No test results found in range.');
            Log::channel('ai-command')->info('RecheckIncompletePanels: no records found', [
                'from' => $fromDate->toDateString(),
                'to' => $toDate->toDateString(),
                'offset' => $offset ?? 0,
            ]);

            return Command::SUCCESS;
        }

        $this->info("Total matched: {$totalMatched} record(s). Will check: {$count}" . ($offset ? " (offset by --offset={$offset})" : '') . ($limit ? " (limited by --limit={$limit})" : '') . '.');
        $this->line('');

        $previewRows = [];
        $incompleteCount = 0;

        foreach ($testResults as $testResult) {
            $result = $panelCompletenessService->evaluate($testResult);

            if (!$result['is_complete']) {
                $incompleteCount++;
            }

            $previewRows[] = [
                $testResult->id,
                $testResult->lab_no ?? 'N/A',
                $testResult->collected_date ? $testResult->collected_date->format('Y-m-d') : 'N/A',
                $testResult->patient->icno ?? 'N/A',
                $result['expected_panel_count'],
                $result['actual_panel_count'],
                $result['is_complete'] ? 'OK' : 'INCOMPLETE',
            ];
        }

        // Always show preview table
        $this->table(
            ['ID', 'Lab No', 'Collected Date', 'Patient IC', 'Expected Panels', 'Actual Panels', 'Status'],
            $previewRows
        );

        $this->info("Found {$incompleteCount} incomplete record(s) out of {$count} checked.");

        if ($dryRun) {
            $this->line('');
            $this->info('DRY RUN — no changes made.');
            Log::channel('ai-command')->info('RecheckIncompletePanels: dry run completed', [
                'checked_count' => $count,
                'incomplete_count' => $incompleteCount,
            ]);

            return Command::SUCCESS;
        }

        if ($incompleteCount === 0) {
            Log::channel('ai-command')->info('RecheckIncompletePanels: no incomplete records to revert', [
                'checked_count' => $count,
            ]);

            return Command::SUCCESS;
        }

        $this->line('');
        if (!$force && !$this->confirm("Proceed with rechecking {$count} record(s)? Records found incomplete will have is_completed reverted to false and recorded in incomplete_test_results.")) {
            $this->info('Operation cancelled.');
            Log::channel('ai-command')->info('RecheckIncompletePanels: cancelled by user');

            return Command::SUCCESS;
        }

        $startTime = microtime(true);
        $reverted = 0;
        $failed = 0;
        $resultRows = [];

        foreach ($testResults as $testResult) {
            Log::channel('ai-command')->info('RecheckIncompletePanels: processing record', [
                'test_result_id' => $testResult->id,
            ]);

            try {
                $isComplete = $panelCompletenessService->checkAndHandle($testResult);

                if ($isComplete) {
                    $resultRows[] = [$testResult->id, 'OK', '-'];
                } else {
                    $reverted++;
                    $resultRows[] = [$testResult->id, 'REVERTED', '-'];
                }

                Log::channel('ai-command')->info('RecheckIncompletePanels: record completed', [
                    'test_result_id' => $testResult->id,
                    'is_complete' => $isComplete,
                ]);
            } catch (Throwable $e) {
                $failed++;
                $resultRows[] = [$testResult->id, 'FAILED', $e->getMessage()];

                Log::channel('ai-command')->error('RecheckIncompletePanels: record failed', [
                    'test_result_id' => $testResult->id,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }
        }

        $duration = round(microtime(true) - $startTime, 2);

        $this->line('');
        $this->table(['ID', 'Status', 'Error'], $resultRows);
        $this->line('');
        $this->info("Done. Reverted: {$reverted}, Failed: {$failed}, Duration: {$duration}s.");

        Log::channel('ai-command')->info('RecheckIncompletePanels completed', [
            'checked_count' => $count,
            'reverted' => $reverted,
            'failed' => $failed,
            'duration_seconds' => $duration,
        ]);

        return $failed > 0 && $reverted === 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
