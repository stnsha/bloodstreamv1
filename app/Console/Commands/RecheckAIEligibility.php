<?php

namespace App\Console\Commands;

use App\Models\TestResult;
use App\Services\PanelCompletenessService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecheckAIEligibility extends Command
{
    protected $signature = 'panels:recheck-ai-eligibility
                            {--from= : Start of collected_date range (Y-m-d), omit for no lower bound}
                            {--to=   : End of collected_date range (Y-m-d), omit for no upper bound}
                            {--limit= : Maximum number of records to process (omit to process all)}
                            {--offset= : Number of matched records to skip before applying --limit, for paging through a large range in batches}
                            {--dry-run : Preview affected records without making any changes}
                            {--force : Skip the confirmation prompt (required for unattended/scheduled runs)}
                            {--prioritize-ref-id : Process records with a non-null ref_id before those without, for higher-fidelity ODB-based completeness checks}';

    protected $description = 'Re-check test results marked is_completed=true and is_reviewed=false against the current panel/invoice/special-test completeness rules (PanelCompletenessService::resolve), reverting is_completed to false and recording any that no longer qualify in incomplete_test_results';

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
        $prioritizeRefId = $this->option('prioritize-ref-id');

        $fromDate = $from ? Carbon::parse($from)->startOfDay() : null;
        $toDate = $to ? Carbon::parse($to)->endOfDay() : null;

        Log::channel('ai-command')->info('RecheckAIEligibility started', [
            'from' => $fromDate?->toDateTimeString() ?? 'unbounded',
            'to' => $toDate?->toDateTimeString() ?? 'unbounded',
            'limit' => $limit ?? 'all',
            'offset' => $offset ?? 0,
            'dry_run' => $dryRun,
            'force' => $force,
            'prioritize_ref_id' => $prioritizeRefId,
        ]);

        $this->info('Querying is_completed=true, is_reviewed=false test results to recheck...');

        $query = TestResult::where('is_completed', true)
            ->where('is_reviewed', false)
            ->when($fromDate && $toDate, fn ($q) => $q->whereBetween('collected_date', [$fromDate, $toDate]))
            ->when($fromDate && ! $toDate, fn ($q) => $q->where('collected_date', '>=', $fromDate))
            ->when(! $fromDate && $toDate, fn ($q) => $q->where('collected_date', '<=', $toDate));

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
            $this->info('No is_completed=true, is_reviewed=false test results found in range.');
            Log::channel('ai-command')->info('RecheckAIEligibility: no records found', [
                'from' => $fromDate?->toDateString() ?? 'unbounded',
                'to' => $toDate?->toDateString() ?? 'unbounded',
                'offset' => $offset ?? 0,
            ]);

            return Command::SUCCESS;
        }

        $this->info("Total matched: {$totalMatched} record(s). Will check: {$count}".($offset ? " (offset by --offset={$offset})" : '').($limit ? " (limited by --limit={$limit})" : '').'.');
        $this->line('');

        $previewRows = [];
        $ineligibleCount = 0;

        foreach ($testResults as $testResult) {
            $result = $panelCompletenessService->evaluateFull($testResult);

            if (! $result['final_is_complete']) {
                $ineligibleCount++;
            }

            $previewRows[] = [
                $testResult->id,
                $testResult->lab_no ?? 'N/A',
                $testResult->collected_date ? $testResult->collected_date->format('Y-m-d') : 'N/A',
                $testResult->patient->icno ?? 'N/A',
                $result['test_result_profiles_count'] > 0 ? 'Yes' : 'No',
                $result['expected_panel_count'],
                $result['actual_panel_count'],
                $result['final_is_complete'] ? 'OK' : 'INELIGIBLE',
                $result['reason'] ?? '-',
            ];
        }

        // Always show preview table
        $this->table(
            ['ID', 'Lab No', 'Collected Date', 'Patient IC', 'Has Profile', 'Expected Panels', 'Actual Panels', 'Status', 'Reason'],
            $previewRows
        );

        $this->info("Found {$ineligibleCount} ineligible record(s) out of {$count} checked.");

        if ($dryRun) {
            $this->line('');
            $this->info('DRY RUN — no changes made.');
            Log::channel('ai-command')->info('RecheckAIEligibility: dry run completed', [
                'checked_count' => $count,
                'ineligible_count' => $ineligibleCount,
            ]);

            return Command::SUCCESS;
        }

        if ($ineligibleCount === 0) {
            Log::channel('ai-command')->info('RecheckAIEligibility: no ineligible records to revert', [
                'checked_count' => $count,
            ]);

            return Command::SUCCESS;
        }

        $this->line('');
        if (! $force && ! $this->confirm("Proceed with rechecking {$count} record(s)? Records found ineligible will have is_completed reverted to false and recorded in incomplete_test_results.")) {
            $this->info('Operation cancelled.');
            Log::channel('ai-command')->info('RecheckAIEligibility: cancelled by user');

            return Command::SUCCESS;
        }

        $startTime = microtime(true);
        $reverted = 0;
        $failed = 0;
        $resultRows = [];

        foreach ($testResults as $testResult) {
            Log::channel('ai-command')->info('RecheckAIEligibility: processing record', [
                'test_result_id' => $testResult->id,
            ]);

            try {
                $isComplete = $panelCompletenessService->resolve($testResult);

                if ($isComplete) {
                    $resultRows[] = [$testResult->id, 'OK', '-'];
                } else {
                    $reverted++;
                    $resultRows[] = [$testResult->id, 'REVERTED', '-'];
                }

                Log::channel('ai-command')->info('RecheckAIEligibility: record completed', [
                    'test_result_id' => $testResult->id,
                    'is_complete' => $isComplete,
                ]);
            } catch (Throwable $e) {
                $failed++;
                $resultRows[] = [$testResult->id, 'FAILED', $e->getMessage()];

                Log::channel('ai-command')->error('RecheckAIEligibility: record failed', [
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

        Log::channel('ai-command')->info('RecheckAIEligibility completed', [
            'checked_count' => $count,
            'reverted' => $reverted,
            'failed' => $failed,
            'duration_seconds' => $duration,
        ]);

        return $failed > 0 && $reverted === 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
