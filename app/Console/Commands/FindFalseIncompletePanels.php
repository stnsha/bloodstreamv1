<?php

namespace App\Console\Commands;

use App\Models\IncompleteTestResult;
use App\Services\PanelCompletenessService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FindFalseIncompletePanels extends Command
{
    protected $signature = 'panels:find-false-incomplete
                            {--from= : Start of collected_date range (Y-m-d), defaults to 30 days ago}
                            {--to=   : End of collected_date range (Y-m-d), defaults to today}
                            {--limit= : Maximum number of records to list (omit to list all)}
                            {--dry-run : Accepted for consistency with the other panels:* commands; this command is always read-only and never makes changes regardless of this flag}
                            {--prioritize-ref-id : Order matched records so those with a non-null ref_id are listed before those without, before --limit is applied}';

    protected $description = 'Read-only audit: list incomplete_test_results whose stored actual_panel_count already satisfies panel completeness (>= '.PanelCompletenessService::COMPLETE_PANEL_THRESHOLD.', or >= expected_panel_count) but are still flagged is_completed=false and is_reviewed=false — candidates to restore with panels:revert-incomplete. Always read-only, makes no changes.';

    public function handle(): int
    {
        $from = $this->option('from') ?? now()->subDays(30)->toDateString();
        $to = $this->option('to') ?? now()->toDateString();
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $dryRun = $this->option('dry-run');
        $prioritizeRefId = $this->option('prioritize-ref-id');

        $fromDate = Carbon::parse($from)->startOfDay();
        $toDate = Carbon::parse($to)->endOfDay();

        Log::channel('ai-command')->info('FindFalseIncompletePanels started', [
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
            Log::channel('ai-command')->info('FindFalseIncompletePanels: no records found', [
                'from' => $fromDate->toDateString(),
                'to' => $toDate->toDateString(),
            ]);

            return Command::SUCCESS;
        }

        $this->info("Total matched: {$totalMatched} record(s). Listing: {$count}".($limit ? " (limited by --limit={$limit})" : '').'.');
        $this->line('');

        $rows = [];

        foreach ($records as $record) {
            $testResult = $record->testResult;

            $reasons = [];

            if ($record->actual_panel_count >= PanelCompletenessService::COMPLETE_PANEL_THRESHOLD) {
                $reasons[] = 'ACTUAL>='.PanelCompletenessService::COMPLETE_PANEL_THRESHOLD;
            }

            if ($record->expected_panel_count > 0 && $record->actual_panel_count >= $record->expected_panel_count) {
                $reasons[] = 'ACTUAL>=EXPECTED';
            }

            $rows[] = [
                $testResult->id ?? $record->test_result_id,
                $testResult->lab_no ?? 'N/A',
                $testResult->ref_id ?? 'N/A',
                $testResult && $testResult->collected_date ? $testResult->collected_date->format('Y-m-d') : 'N/A',
                $testResult->patient->icno ?? 'N/A',
                $record->expected_panel_count,
                $record->actual_panel_count,
                implode(' & ', $reasons),
                $record->created_at->format('Y-m-d H:i'),
            ];
        }

        $this->table(
            ['Test Result ID', 'Lab No', 'Ref ID', 'Collected Date', 'Patient IC', 'Expected Panels', 'Actual Panels', 'Matched Reason', 'Flagged At'],
            $rows
        );

        $this->line('');
        $this->info("Found {$count} false-positive candidate(s) — read-only, no changes made.");

        if ($dryRun) {
            $this->info('DRY RUN — no changes made (this command is always read-only regardless of --dry-run).');
        }

        $this->info('To restore these, run: php artisan panels:fix-false-incomplete (with the same --from/--to range).');

        Log::channel('ai-command')->info('FindFalseIncompletePanels completed', [
            'matched_count' => $count,
            'test_result_ids' => $records->pluck('test_result_id')->all(),
        ]);

        return Command::SUCCESS;
    }
}
