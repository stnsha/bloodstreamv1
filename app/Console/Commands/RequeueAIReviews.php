<?php

namespace App\Console\Commands;

use App\Jobs\SendToAIServer;
use App\Models\AIReview;
use App\Models\TestResult;
use App\Services\PanelCompletenessService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class RequeueAIReviews extends Command
{
    protected $signature = 'ai-review:requeue
                            {ids : Comma-separated test_result IDs to delete the existing AI review for and re-dispatch}
                            {--dry-run : Preview affected test results without making any changes}
                            {--force : Skip the confirmation prompt (required for unattended/scheduled runs)}';

    protected $description = 'Soft-delete the current ai_reviews row for the given test_result IDs, reset is_reviewed to false, and re-dispatch SendToAIServer to generate a fresh review.';

    public function handle(PanelCompletenessService $panelCompletenessService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $ids = collect(explode(',', $this->argument('ids')))
            ->map(fn ($id) => trim($id))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            $this->error('No valid test_result IDs provided.');

            return self::FAILURE;
        }

        Log::channel('ai-command')->info('RequeueAIReviews: started', [
            'ids' => $ids->all(),
            'dry_run' => $dryRun,
        ]);

        $testResults = TestResult::whereIn('id', $ids)->get()->keyBy('id');

        $rows = [];

        foreach ($ids as $id) {
            $testResult = $testResults->get($id);

            if (! $testResult) {
                $rows[] = [$id, 'N/A', 'SKIP — test result not found'];
                continue;
            }

            $activeReview = AIReview::where('test_result_id', $id)->first();

            $rows[] = [
                $id,
                $testResult->lab_no,
                $activeReview
                    ? "WILL DELETE REVIEW (id={$activeReview->id}, status={$activeReview->processing_status}) AND REQUEUE"
                    : 'WILL REQUEUE (no active review found)',
            ];
        }

        $this->table(['Test Result ID', 'Lab No', 'Action'], $rows);
        $this->line('');

        $eligibleCount = $testResults->count();

        if ($dryRun) {
            $this->info("DRY RUN — no changes made. {$eligibleCount} of {$ids->count()} test result(s) found and would be requeued.");

            Log::channel('ai-command')->info('RequeueAIReviews: dry run completed', [
                'requested' => $ids->count(),
                'found' => $eligibleCount,
            ]);

            return self::SUCCESS;
        }

        if (! $force && ! $this->confirm("Delete existing AI review and re-queue {$eligibleCount} test result(s)?")) {
            $this->info('Operation cancelled.');
            Log::channel('ai-command')->info('RequeueAIReviews: cancelled by user');

            return self::SUCCESS;
        }

        $requeued = 0;
        $skippedNotFound = 0;
        $failed = 0;
        $resultRows = [];

        foreach ($ids as $id) {
            $testResult = $testResults->get($id);

            if (! $testResult) {
                $skippedNotFound++;
                $resultRows[] = [$id, 'SKIPPED', 'Test result not found'];
                continue;
            }

            try {
                $panelCompletenessService->revertReviewForLateData($testResult);

                SendToAIServer::dispatch($testResult->id);

                $requeued++;
                $resultRows[] = [$id, 'REQUEUED', "lab_no={$testResult->lab_no}"];

                Log::channel('ai-command')->info('RequeueAIReviews: review deleted and requeued', [
                    'test_result_id' => $id,
                    'lab_no' => $testResult->lab_no,
                ]);
            } catch (Throwable $e) {
                $failed++;
                $resultRows[] = [$id, 'FAILED', $e->getMessage()];

                Log::channel('ai-command')->error('RequeueAIReviews: failed to requeue', [
                    'test_result_id' => $id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->line('');
        $this->table(['Test Result ID', 'Status', 'Detail'], $resultRows);
        $this->line('');
        $this->info("Done. Requeued: {$requeued}, Skipped (not found): {$skippedNotFound}, Failed: {$failed}.");

        Log::channel('ai-command')->info('RequeueAIReviews: completed', [
            'requeued' => $requeued,
            'skipped_not_found' => $skippedNotFound,
            'failed' => $failed,
        ]);

        return $failed > 0 && $requeued === 0 ? self::FAILURE : self::SUCCESS;
    }
}
