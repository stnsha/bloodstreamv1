<?php

namespace App\Console\Commands;

use App\Models\AIReview;
use App\Models\TestResult;
use App\Services\TestResultCompilerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ManualReviewByIds extends Command
{
    protected $signature = 'ai:manual-review
                            {ids* : One or more test result IDs (space-separated)}
                            {--dry-run : Preview what would be inserted without writing to the database}';

    protected $description = 'Insert compiled test data into ai_reviews and mark test results as reviewed, bypassing the AI API';

    public $timeout = 0;

    protected TestResultCompilerService $compiler;

    public function __construct(TestResultCompilerService $compiler)
    {
        parent::__construct();
        $this->compiler = $compiler;
    }

    public function handle(): int
    {
        $ids = array_map('intval', $this->argument('ids'));
        $dryRun = $this->option('dry-run');

        $processed = 0;
        $skipped = 0;
        $failed = 0;

        Log::channel('ai-command')->info('Manual review command started', [
            'ids' => $ids,
            'total' => count($ids),
            'dry_run' => $dryRun,
        ]);

        if ($dryRun) {
            $this->warn('DRY RUN — no data will be written');
            $this->newLine();
        }

        foreach ($ids as $id) {
            $testResult = TestResult::find($id);

            if (! $testResult) {
                $this->warn("ID {$id}: not found — skipping");
                Log::channel('ai-command')->warning('Test result not found', ['test_result_id' => $id]);
                $skipped++;
                continue;
            }

            if (! $testResult->is_completed) {
                $this->warn("ID {$id}: not completed (is_completed = false) — skipping");
                Log::channel('ai-command')->warning('Test result not completed', ['test_result_id' => $id]);
                $skipped++;
                continue;
            }

            $hasCompleted = AIReview::where('test_result_id', $id)
                ->where('processing_status', 'COMPLETED')
                ->whereNull('deleted_at')
                ->exists();

            if ($hasCompleted) {
                $this->line("ID {$id}: COMPLETED ai_review already exists — skipping");
                Log::channel('ai-command')->info('Skipped — COMPLETED review exists', ['test_result_id' => $id]);
                $skipped++;
                continue;
            }

            $existingStatus = AIReview::where('test_result_id', $id)
                ->whereNull('deleted_at')
                ->value('processing_status');

            if ($dryRun) {
                try {
                    $testResultModel = $this->compiler->fetchTestResult($id);
                    $compiledData = $this->compiler->compileTestResultData($testResultModel, 'MANUAL');

                    $this->line("ID {$id}:");
                    $this->line("  is_completed         : true");
                    $this->line("  is_reviewed          : " . ($testResult->is_reviewed ? 'true (will overwrite)' : 'false'));
                    $this->line("  existing review      : " . ($existingStatus ?? 'none'));
                    $this->line("  action               : INSERT/UPDATE ai_reviews with processing_status=COMPLETED");
                    $this->line("  action               : SET test_results.is_reviewed = true");
                    $this->newLine();
                    $this->line("  --- compiled_data / ai_response preview ---");
                    $this->line(json_encode($compiledData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    $this->newLine();

                    $processed++;
                } catch (Throwable $e) {
                    $this->error("ID {$id}: compile failed — {$e->getMessage()}");
                    Log::channel('ai-command')->error('Dry run compile failed', [
                        'test_result_id' => $id,
                        'error'          => $e->getMessage(),
                    ]);
                    $failed++;
                }
                continue;
            }

            $this->line("Processing ID {$id} ...");

            try {
                DB::beginTransaction();

                $testResultModel = $this->compiler->fetchTestResult($id);
                $compiledData = $this->compiler->compileTestResultData($testResultModel, 'MANUAL');

                AIReview::updateOrCreate(
                    ['test_result_id' => $id],
                    [
                        'processing_status' => 'COMPLETED',
                        'compiled_results'  => $compiledData,
                        'http_status'       => 200,
                        'ai_response'       => $compiledData,
                        'raw_response'      => null,
                    ]
                );

                $testResultModel->is_reviewed = true;
                $testResultModel->save();

                DB::commit();

                $this->info("  OK");
                Log::channel('ai-command')->info('Manual review inserted', [
                    'test_result_id' => $id,
                ]);
                $processed++;
            } catch (Throwable $e) {
                DB::rollBack();

                $this->error("  FAILED: {$e->getMessage()}");
                Log::channel('ai-command')->error('Manual review failed', [
                    'test_result_id' => $id,
                    'exception_class' => get_class($e),
                    'error'          => $e->getMessage(),
                    'file'           => $e->getFile() . ':' . $e->getLine(),
                ]);
                $failed++;
            }
        }

        $this->newLine();

        if ($dryRun) {
            $this->warn("DRY RUN complete: {$processed} would be processed, {$skipped} would be skipped");
        } else {
            $this->line("Done: {$processed} processed, {$skipped} skipped, {$failed} failed");
        }

        Log::channel('ai-command')->info('Manual review command completed', [
            'total'     => count($ids),
            'processed' => $processed,
            'skipped'   => $skipped,
            'failed'    => $failed,
            'dry_run'   => $dryRun,
        ]);

        return $failed > 0 && $processed === 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
