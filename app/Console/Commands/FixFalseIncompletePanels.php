<?php

namespace App\Console\Commands;

use App\Models\IncompleteTestResult;
use App\Services\PanelCompletenessService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class FixFalseIncompletePanels extends Command
{
    protected $signature = 'panels:fix-false-incomplete
                            {--from= : Start of collected_date range (Y-m-d), defaults to 30 days ago}
                            {--to=   : End of collected_date range (Y-m-d), defaults to today}
                            {--limit= : Maximum number of records to process (omit to process all)}
                            {--dry-run : Preview matched records without making any changes or prompting for confirmation}
                            {--prioritize-ref-id : Order matched records so those with a non-null ref_id are processed before those without, before --limit is applied}';

    protected $description = 'Restore (is_completed=1) the incomplete_test_results whose stored actual_panel_count already satisfies panel completeness (>= '.PanelCompletenessService::COMPLETE_PANEL_THRESHOLD.', or >= expected_panel_count) — the same false-positive set listed by panels:find-false-incomplete. Prompts for yes/no confirmation before making any change.';

    public function handle(PanelCompletenessService $panelCompletenessService): int
    {
        $from = $this->option('from') ?? now()->subDays(30)->toDateString();
        $to = $this->option('to') ?? now()->toDateString();
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $dryRun = $this->option('dry-run');
        $prioritizeRefId = $this->option('prioritize-ref-id');

        $fromDate = Carbon::parse($from)->startOfDay();
        $toDate = Carbon::parse($to)->endOfDay();

        Log::channel('ai-command')->info('FixFalseIncompletePanels started', [
            'from' => $fromDate->toDateTimeString(),
            'to' => $toDate->toDateTimeString(),
            'limit' => $limit ?? 'all',
            'dry_run' => $dryRun,
            'prioritize_ref_id' => $prioritizeRefId,
        ]);

        $this->info('Querying incomplete_test_results for false-positive candidates...');

        $baseQuery = IncompleteTestResult::query()
            ->join('test_results', 'test_results.id', '=', 'incomplete_test_results.test_result_id')
            ->whereBetween('test_results.collected_date', [$fromDate, $toDate])
            ->where('test_results.is_completed', false)
            ->where('test_results.is_reviewed', false)
            ->where(function ($q) {
                $q->whereNull('incomplete_test_results.reason')
                    ->orWhere('incomplete_test_results.reason', 'panel_count');
            })
            ->where(function ($q) {
                $q->where('incomplete_test_results.actual_panel_count', '>=', PanelCompletenessService::COMPLETE_PANEL_THRESHOLD)
                    ->orWhere(function ($q2) {
                        $q2->where('incomplete_test_results.expected_panel_count', '>', 0)
                            ->whereColumn('incomplete_test_results.actual_panel_count', '>=', 'incomplete_test_results.expected_panel_count');
                    });
            });

        $totalMatched = (clone $baseQuery)->count();

        $records = $baseQuery
            ->select('incomplete_test_results.*')
            ->with(['testResult.patient:id,icno'])
            ->when($prioritizeRefId, fn ($q) => $q->orderByRaw('test_results.ref_id IS NULL'))
            ->orderBy('test_results.collected_date', 'desc')
            ->when($limit, fn ($q) => $q->limit($limit))
            ->get();

        $count = $records->count();

        if ($count === 0) {
            $this->info('No false-positive incomplete records found in range.');
            Log::channel('ai-command')->info('FixFalseIncompletePanels: no records found', [
                'from' => $fromDate->toDateString(),
                'to' => $toDate->toDateString(),
            ]);

            return Command::SUCCESS;
        }

        $this->info("Total matched: {$totalMatched} record(s). Will restore: {$count}".($limit ? " (limited by --limit={$limit})" : '').'.');
        $this->line('');

        $previewRows = [];

        foreach ($records as $record) {
            $testResult = $record->testResult;

            $reasons = [];

            if ($record->actual_panel_count >= PanelCompletenessService::COMPLETE_PANEL_THRESHOLD) {
                $reasons[] = 'ACTUAL>='.PanelCompletenessService::COMPLETE_PANEL_THRESHOLD;
            }

            if ($record->expected_panel_count > 0 && $record->actual_panel_count >= $record->expected_panel_count) {
                $reasons[] = 'ACTUAL>=EXPECTED';
            }

            $previewRows[] = [
                $testResult->id ?? $record->test_result_id,
                $testResult->lab_no ?? 'N/A',
                $testResult->ref_id ?? 'N/A',
                $testResult && $testResult->collected_date ? $testResult->collected_date->format('Y-m-d') : 'N/A',
                $testResult->patient->icno ?? 'N/A',
                $record->expected_panel_count,
                $record->actual_panel_count,
                implode(' & ', $reasons),
            ];
        }

        $this->table(
            ['Test Result ID', 'Lab No', 'Ref ID', 'Collected Date', 'Patient IC', 'Expected Panels', 'Actual Panels', 'Matched Reason'],
            $previewRows
        );

        $this->line('');

        if ($dryRun) {
            $this->info('DRY RUN — no changes made.');
            Log::channel('ai-command')->info('FixFalseIncompletePanels: dry run completed', [
                'matched_count' => $count,
            ]);

            return Command::SUCCESS;
        }

        if (! $this->confirm("Restore is_completed=1 for these {$count} record(s)? This also restores is_reviewed and any soft-deleted AI review to their pre-revert state, and removes them from incomplete_test_results.")) {
            $this->info('Operation cancelled.');
            Log::channel('ai-command')->info('FixFalseIncompletePanels: cancelled by user');

            return Command::SUCCESS;
        }

        $startTime = microtime(true);
        $fixed = 0;
        $failed = 0;
        $resultRows = [];

        foreach ($records as $record) {
            $testResult = $record->testResult;

            if (! $testResult) {
                $failed++;
                $resultRows[] = [$record->test_result_id, 'FAILED', 'Test result not found'];

                continue;
            }

            Log::channel('ai-command')->info('FixFalseIncompletePanels: processing record', [
                'test_result_id' => $testResult->id,
            ]);

            try {
                $wasFixed = $panelCompletenessService->undo($testResult);

                if ($wasFixed) {
                    $fixed++;
                    $resultRows[] = [$testResult->id, 'RESTORED', '-'];
                } else {
                    $resultRows[] = [$testResult->id, 'SKIPPED', 'Nothing to undo'];
                }

                Log::channel('ai-command')->info('FixFalseIncompletePanels: record completed', [
                    'test_result_id' => $testResult->id,
                    'restored' => $wasFixed,
                ]);
            } catch (Throwable $e) {
                $failed++;
                $resultRows[] = [$testResult->id, 'FAILED', $e->getMessage()];

                Log::channel('ai-command')->error('FixFalseIncompletePanels: record failed', [
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
        $this->info("Done. Restored: {$fixed}, Failed: {$failed}, Duration: {$duration}s.");

        Log::channel('ai-command')->info('FixFalseIncompletePanels completed', [
            'matched_count' => $count,
            'restored' => $fixed,
            'failed' => $failed,
            'duration_seconds' => $duration,
        ]);

        return $failed > 0 && $fixed === 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
