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
                            {ids? : Comma-separated test_result IDs to delete the existing AI review for and re-dispatch. Omit to use the hardcoded DEFAULT_IDS list.}
                            {--dry-run : Preview affected test results without making any changes}
                            {--force : Skip the confirmation prompt (required for unattended/scheduled runs)}';

    protected $description = 'Soft-delete the current ai_reviews row for the given test_result IDs, reset is_reviewed to false, and re-dispatch SendToAIServer to generate a fresh review.';

    /**
     * test_result IDs where is_reviewed=1 but the active ai_reviews row was
     * created before manually_completed_at (from the 2026-08-14 lab-no
     * mark-completed-from-excel run against
     * IQMY-Alpro-incomplete_test_results_2026-08-13_093535.xlsx) — the AI
     * review is stale relative to the manual completion and needs
     * regenerating.
     */
    private const DEFAULT_IDS = [
        44502, 44584, 44586, 44587, 44588, 44591, 44657, 44943, 44994, 45293,
        45407, 45408, 45811, 45853, 45867, 45873, 45884, 45957, 45959, 45960,
        45961, 46447, 46532, 46708, 47116, 47564, 47650, 48067, 48143, 48543,
        48550, 48680, 48682, 48685, 48691, 48918, 48920, 48921, 48922, 48926,
        48927, 48952, 48968, 48969, 48971, 48979, 48989, 48990, 48991,
    ];

    public function handle(PanelCompletenessService $panelCompletenessService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $idsArg = $this->argument('ids');

        $ids = $idsArg === null
            ? collect(self::DEFAULT_IDS)
            : collect(explode(',', $idsArg))
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
