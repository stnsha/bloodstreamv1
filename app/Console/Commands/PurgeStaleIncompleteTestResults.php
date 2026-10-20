<?php

namespace App\Console\Commands;

use App\Models\IncompleteTestResult;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PurgeStaleIncompleteTestResults extends Command
{
    protected $signature = 'panels:purge-stale-incomplete
                            {--limit= : Maximum number of records to purge (omit to purge all)}
                            {--dry-run : Preview affected records without making any changes}
                            {--force : Skip the confirmation prompt (required for unattended/scheduled runs)}';

    protected $description = 'Delete incomplete_test_results rows whose test_results record is already is_completed=1 AND is_reviewed=1. Such rows are stale leftovers (the record was completed and reviewed through a path that did not clear its incomplete flag) and keep showing on the ODB Incomplete Test Results page. Only the incomplete_test_results row is removed; test_results and ai_reviews are not touched.';

    public function handle(): int
    {
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        Log::channel('ai-command')->info('PurgeStaleIncompleteTestResults started', [
            'limit' => $limit ?? 'all',
            'dry_run' => $dryRun,
            'force' => $force,
        ]);

        $staleRecords = IncompleteTestResult::query()
            ->whereHas('testResult', fn ($q) => $q->where('is_completed', true)->where('is_reviewed', true))
            ->with('testResult:id,lab_no,ref_id')
            ->orderBy('id')
            ->when($limit, fn ($q) => $q->limit($limit))
            ->get();

        $count = $staleRecords->count();

        if ($count === 0) {
            $this->info('No stale incomplete_test_results found.');
            Log::channel('ai-command')->info('PurgeStaleIncompleteTestResults: no records found');

            return Command::SUCCESS;
        }

        $this->table(
            ['Incomplete ID', 'Test Result ID', 'Lab No', 'Ref ID', 'Stored Reason'],
            $staleRecords->map(fn (IncompleteTestResult $record) => [
                $record->id,
                $record->test_result_id,
                $record->testResult->lab_no ?? 'N/A',
                $record->testResult->ref_id ?? 'N/A',
                $record->reason ?? '-',
            ])->all()
        );

        $this->info("{$count} stale record(s) found (test result already completed and reviewed).");

        if ($dryRun) {
            $this->line('');
            $this->info('DRY RUN — no changes made.');
            Log::channel('ai-command')->info('PurgeStaleIncompleteTestResults: dry run completed', [
                'matched_count' => $count,
            ]);

            return Command::SUCCESS;
        }

        $this->line('');
        if (! $force && ! $this->confirm("Delete {$count} stale incomplete_test_results row(s)?")) {
            $this->info('Operation cancelled.');
            Log::channel('ai-command')->info('PurgeStaleIncompleteTestResults: cancelled by user');

            return Command::SUCCESS;
        }

        $ids = $staleRecords->pluck('id')->all();
        $testResultIds = $staleRecords->pluck('test_result_id')->all();

        try {
            DB::beginTransaction();

            $deleted = IncompleteTestResult::whereIn('id', $ids)->delete();

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            Log::channel('ai-command')->error('PurgeStaleIncompleteTestResults: failed to delete stale records', [
                'test_result_ids' => $testResultIds,
                'error' => $e->getMessage(),
            ]);

            $this->error('Failed to delete stale records: '.$e->getMessage());

            return Command::FAILURE;
        }

        Log::channel('ai-command')->info('PurgeStaleIncompleteTestResults completed', [
            'deleted_count' => $deleted,
            'test_result_ids' => $testResultIds,
        ]);

        $this->info("Deleted {$deleted} stale incomplete_test_results row(s).");

        return Command::SUCCESS;
    }
}
