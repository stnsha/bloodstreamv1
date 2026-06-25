<?php

namespace App\Console\Commands;

use App\Models\AIReview;
use App\Models\TestResult;
use App\Services\AIReviewService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReviewByIds extends Command
{
    protected $signature = 'ai:force-review
                            {ids* : One or more test result IDs (space-separated)}
                            {--force : Re-process even if already reviewed}';

    protected $description = 'Force synchronous AI review for specific test result IDs, bypassing the queue';

    public $timeout = 0;

    protected AIReviewService $aiReviewService;

    public function __construct(AIReviewService $aiReviewService)
    {
        parent::__construct();
        $this->aiReviewService = $aiReviewService;
    }

    public function handle(): int
    {
        $ids = array_map('intval', $this->argument('ids'));
        $force = $this->option('force');

        $processed = 0;
        $skipped = 0;
        $failed = 0;

        Log::channel('ai-command')->info('Force review command started', [
            'ids' => $ids,
            'force' => $force,
            'total' => count($ids),
        ]);

        foreach ($ids as $id) {
            $label = "ID {$id}";

            $testResult = TestResult::find($id);

            if (! $testResult) {
                $this->warn("{$label}: not found — skipping");
                Log::channel('ai-command')->warning('Test result not found', ['test_result_id' => $id]);
                $skipped++;
                continue;
            }

            if (! $testResult->is_completed) {
                $this->warn("{$label}: not completed — skipping");
                Log::channel('ai-command')->warning('Test result not completed', ['test_result_id' => $id]);
                $skipped++;
                continue;
            }

            if (! $force) {
                if ($testResult->is_reviewed) {
                    $this->line("{$label}: already reviewed — skipping (use --force to re-process)");
                    Log::channel('ai-command')->info('Skipped already-reviewed result', ['test_result_id' => $id]);
                    $skipped++;
                    continue;
                }

                $hasCompleted = AIReview::where('test_result_id', $id)
                    ->where('processing_status', 'COMPLETED')
                    ->whereNull('deleted_at')
                    ->exists();

                if ($hasCompleted) {
                    $this->line("{$label}: COMPLETED ai_review exists — skipping (use --force to re-process)");
                    Log::channel('ai-command')->info('Skipped result with existing COMPLETED review', ['test_result_id' => $id]);
                    $skipped++;
                    continue;
                }
            }

            $icno = $testResult->patient->icno ?? null;
            $refId = $testResult->ref_id ?? null;

            $context = implode(', ', array_filter([
                $icno ? "ICNO: {$icno}" : null,
                $refId ? "REF: {$refId}" : null,
            ]));

            $this->line("Processing {$label}" . ($context ? " ({$context})" : '') . ' ...');

            try {
                $result = $this->aiReviewService->processSingle($id, 'CMD');

                if ($result->isSuccessful()) {
                    $this->info("  OK");
                    Log::channel('ai-command')->info('Force review succeeded', [
                        'test_result_id' => $id,
                        'icno' => $icno,
                        'ref_id' => $refId,
                    ]);
                    $processed++;
                } else {
                    $this->error("  FAILED: {$result->errorMessage}");
                    Log::channel('ai-command')->error('Force review failed', [
                        'test_result_id' => $id,
                        'error' => $result->errorMessage,
                    ]);
                    $failed++;
                }
            } catch (Throwable $e) {
                $this->error("  FAILED: {$e->getMessage()}");
                Log::channel('ai-command')->error('Force review threw exception', [
                    'test_result_id' => $id,
                    'exception_class' => get_class($e),
                    'error' => $e->getMessage(),
                    'file' => $e->getFile() . ':' . $e->getLine(),
                ]);
                $failed++;
            }
        }

        $this->newLine();
        $this->line("Done: {$processed} processed, {$skipped} skipped, {$failed} failed");

        Log::channel('ai-command')->info('Force review command completed', [
            'total' => count($ids),
            'processed' => $processed,
            'skipped' => $skipped,
            'failed' => $failed,
        ]);

        return $failed > 0 && $processed === 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
