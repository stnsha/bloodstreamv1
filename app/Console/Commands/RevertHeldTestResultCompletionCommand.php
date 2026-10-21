<?php

namespace App\Console\Commands;

use App\Models\TestResult;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class RevertHeldTestResultCompletionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test-result:revert-held-completion
        {--dry-run : Preview affected test results without updating}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set is_completed=0 on test results whose AI review is still held for the consult call '
        . '(CC / CC + AO / AO, not yet released), matching TestResultCompletionDispatcher::holdAiReview(). '
        . 'Results held before that change were left is_completed=1. Release sets is_completed=1 again.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        Log::info('RevertHeldTestResultCompletionCommand: Starting', ['dry_run' => $dryRun]);

        // Held and not yet released by the consult call. is_reviewed=0 only: a held
        // result is never sent to AI review, so a reviewed one is left for a person to check.
        $held = TestResult::query()
            ->whereNotNull('ai_review_held_at')
            ->whereNull('ai_review_released_at')
            ->where('is_completed', true);

        $reviewedCount = (clone $held)->where('is_reviewed', true)->count();
        $targets = (clone $held)
            ->where('is_reviewed', false)
            ->orderBy('id')
            ->get(['id', 'lab_no', 'ref_id', 'ai_review_held_at']);

        $this->line('Held, not released, is_completed=1, is_reviewed=0: ' . $targets->count());
        $this->line('Held, not released, is_completed=1, is_reviewed=1 (skipped, check manually): ' . $reviewedCount);

        if ($targets->isEmpty()) {
            $this->info('No held test results need is_completed reverted.');

            Log::info('RevertHeldTestResultCompletionCommand: Nothing to update');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->table(
                ['Test Result', 'Lab No', 'Ref ID', 'Held At'],
                $targets->map(fn (TestResult $testResult) => [
                    $testResult->id,
                    $testResult->lab_no ?? '-',
                    $testResult->ref_id ?? '-',
                    (string) $testResult->ai_review_held_at,
                ])->all()
            );
            $this->warn("[DRY-RUN] Would set is_completed=0 on {$targets->count()} test result(s).");

            return self::SUCCESS;
        }

        try {
            // Conditions repeated so a result released since the read is not touched.
            $updatedCount = TestResult::query()
                ->whereIn('id', $targets->pluck('id'))
                ->whereNotNull('ai_review_held_at')
                ->whereNull('ai_review_released_at')
                ->where('is_completed', true)
                ->where('is_reviewed', false)
                ->update(['is_completed' => false]);
        } catch (Throwable $e) {
            $this->error('Failed to revert is_completed on held test results: ' . $e->getMessage());

            Log::error('RevertHeldTestResultCompletionCommand: Failed', [
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }

        $this->info("Set is_completed=0 on {$updatedCount} held test result(s).");

        Log::info('RevertHeldTestResultCompletionCommand: Completed', [
            'updated_count' => $updatedCount,
            'test_result_ids' => $targets->pluck('id')->all(),
        ]);

        return self::SUCCESS;
    }
}
