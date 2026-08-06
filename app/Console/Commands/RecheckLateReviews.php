<?php

namespace App\Console\Commands;

use App\Models\TestResult;
use App\Services\PanelCompletenessService;
use App\Services\TestResultCompletionDispatcher;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecheckLateReviews extends Command
{
    protected $signature = 'panels:recheck-late-reviews
                            {--test-result-id= : Recheck a single TestResult by id, ignoring all other filters}
                            {--from= : Start of collected_date range (Y-m-d), omit for no lower bound}
                            {--to=   : End of collected_date range (Y-m-d), omit for no upper bound}
                            {--limit= : Maximum number of records to process (omit to process all)}
                            {--offset= : Number of matched records to skip before applying --limit, for paging through a large range in batches}
                            {--dry-run : Preview affected records without making any changes}
                            {--force : Skip the confirmation prompt (required for unattended/scheduled runs)}';

    protected $description = 'Find test results marked is_completed=true, is_reviewed=true where a test_result_item was created after their AI review completed (a panel arrived too late to be included), soft-delete the stale ai_reviews row via PanelCompletenessService::revertReviewForLateData, and redispatch AI review + consult-call re-eval';

    public function handle(PanelCompletenessService $panelCompletenessService, TestResultCompletionDispatcher $dispatcher): int
    {
        $testResultId = $this->option('test-result-id');
        $from = $this->option('from');
        $to = $this->option('to');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $offset = $this->option('offset') ? (int) $this->option('offset') : null;
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        if ($offset && ! $limit) {
            $this->error('--offset requires --limit to be set (MySQL does not support OFFSET without LIMIT).');

            return Command::FAILURE;
        }

        $fromDate = $from ? Carbon::parse($from)->startOfDay() : null;
        $toDate = $to ? Carbon::parse($to)->endOfDay() : null;

        Log::channel('ai-command')->info('RecheckLateReviews started', [
            'test_result_id' => $testResultId ?? 'all',
            'from' => $fromDate?->toDateTimeString() ?? 'unbounded',
            'to' => $toDate?->toDateTimeString() ?? 'unbounded',
            'limit' => $limit ?? 'all',
            'offset' => $offset ?? 0,
            'dry_run' => $dryRun,
            'force' => $force,
        ]);

        $this->info('Querying is_completed=true, is_reviewed=true test results with panel data newer than their AI review...');

        // A live (non-trashed, COMPLETED) ai_reviews row exists, and at least one
        // test_result_item was created after that review's updated_at (the row is
        // updated in place from PENDING -> COMPLETED by the webhook, so updated_at is
        // the actual completion time) -- i.e. a panel that arrived too late to be
        // included in the review currently on record.
        $lateDataExists = '
            exists (
                select 1 from ai_reviews
                where ai_reviews.test_result_id = test_results.id
                and ai_reviews.deleted_at is null
                and ai_reviews.processing_status = \'COMPLETED\'
                and exists (
                    select 1 from test_result_items
                    where test_result_items.test_result_id = test_results.id
                    and test_result_items.created_at > ai_reviews.updated_at
                )
            )
        ';

        $query = TestResult::query()
            ->where('is_completed', true)
            ->where('is_reviewed', true)
            ->when($testResultId, fn ($q) => $q->where('id', $testResultId))
            ->when($fromDate && $toDate, fn ($q) => $q->whereBetween('collected_date', [$fromDate, $toDate]))
            ->when($fromDate && ! $toDate, fn ($q) => $q->where('collected_date', '>=', $fromDate))
            ->when(! $fromDate && $toDate, fn ($q) => $q->where('collected_date', '<=', $toDate))
            ->whereRaw($lateDataExists);

        $totalMatched = $query->count();

        $testResults = $query
            ->with('patient:id,icno')
            ->orderBy('collected_date', 'desc')
            ->when($offset, fn ($q) => $q->skip($offset))
            ->when($limit, fn ($q) => $q->limit($limit))
            ->get();

        $count = $testResults->count();

        if ($count === 0) {
            $this->info('No affected records found.');
            Log::channel('ai-command')->info('RecheckLateReviews: no affected records found', [
                'test_result_id' => $testResultId ?? 'all',
            ]);

            return Command::SUCCESS;
        }

        $this->info("Total matched: {$totalMatched} record(s). Will process: {$count}".($offset ? " (offset by --offset={$offset})" : '').($limit ? " (limited by --limit={$limit})" : '').'.');
        $this->line('');

        $this->table(
            ['ID', 'Lab No', 'Collected Date', 'Patient IC'],
            $testResults->map(fn ($tr) => [
                $tr->id,
                $tr->lab_no ?? 'N/A',
                $tr->collected_date ? $tr->collected_date->format('Y-m-d') : 'N/A',
                $tr->patient->icno ?? 'N/A',
            ])
        );

        if ($dryRun) {
            $this->line('');
            $this->info('DRY RUN — no changes made.');
            Log::channel('ai-command')->info('RecheckLateReviews: dry run completed', [
                'affected_count' => $count,
            ]);

            return Command::SUCCESS;
        }

        $this->line('');
        if (! $force && ! $this->confirm("Proceed rerunning review for {$count} record(s)? The current ai_reviews row will be soft-deleted (not purged) and a fresh AI review + consult-call re-eval dispatched.")) {
            $this->info('Operation cancelled.');
            Log::channel('ai-command')->info('RecheckLateReviews: cancelled by user');

            return Command::SUCCESS;
        }

        $startTime = microtime(true);
        $processed = 0;
        $failed = 0;
        $rows = [];

        foreach ($testResults as $testResult) {
            try {
                $panelCompletenessService->revertReviewForLateData($testResult);

                if ($panelCompletenessService->resolve($testResult)) {
                    $dispatcher->dispatch($testResult);
                }

                $processed++;
                $rows[] = [$testResult->id, 'REDISPATCHED', '-'];

                Log::channel('ai-command')->info('RecheckLateReviews: record redispatched', [
                    'test_result_id' => $testResult->id,
                ]);
            } catch (Throwable $e) {
                $failed++;
                $rows[] = [$testResult->id, 'FAILED', $e->getMessage()];

                Log::channel('ai-command')->error('RecheckLateReviews: record failed', [
                    'test_result_id' => $testResult->id,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }
        }

        $duration = round(microtime(true) - $startTime, 2);

        $this->line('');
        $this->table(['ID', 'Status', 'Error'], $rows);
        $this->line('');
        $this->info("Done. Redispatched: {$processed}, Failed: {$failed}, Duration: {$duration}s.");

        Log::channel('ai-command')->info('RecheckLateReviews completed', [
            'processed' => $processed,
            'failed' => $failed,
            'duration_seconds' => $duration,
        ]);

        return $failed > 0 && $processed === 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
