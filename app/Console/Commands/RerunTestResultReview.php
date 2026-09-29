<?php

namespace App\Console\Commands;

use App\Models\AIReview;
use App\Models\ConsultCallDetails;
use App\Models\TestResult;
use App\Services\TestResultCompletionDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class RerunTestResultReview extends Command
{
    protected $signature = 'test-result:rerun-review
                            {--lab_no= : Lab number of the test result}
                            {--test_result_id= : Test result ID}
                            {--dry-run : Preview what would happen without making any changes}
                            {--force : Skip the confirmation prompt}';

    protected $description = 'Soft-delete the AI review for one test result and reset is_reviewed=0 so the next scheduled ai:dispatch-unreviewed-async run regenerates it, then run the live-flow consult-call eligibility check (with date gates) if the result is not yet enrolled';

    public function handle(TestResultCompletionDispatcher $dispatcher): int
    {
        $labNo = trim((string) $this->option('lab_no'));
        $testResultId = trim((string) $this->option('test_result_id'));
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if (($labNo === '') === ($testResultId === '')) {
            $this->error('Provide exactly one of --lab_no or --test_result_id.');

            return self::FAILURE;
        }

        if ($testResultId !== '' && ! ctype_digit($testResultId)) {
            $this->error("--test_result_id must be a positive integer, got: {$testResultId}");

            return self::FAILURE;
        }

        Log::channel('ai-command')->info('RerunTestResultReview: started', [
            'lab_no' => $labNo ?: null,
            'test_result_id' => $testResultId ?: null,
            'dry_run' => $dryRun,
        ]);

        $testResult = $testResultId !== ''
            ? $this->resolveById((int) $testResultId)
            : $this->resolveByLabNo($labNo);

        if (! $testResult) {
            return self::FAILURE;
        }

        $activeReviews = AIReview::where('test_result_id', $testResult->id)->get();
        $alreadyEnrolled = $this->isEnrolled($testResult);

        $this->table(['Field', 'Value'], [
            ['Test Result ID', $testResult->id],
            ['Lab No', $testResult->lab_no ?? 'N/A'],
            ['Collected Date', $testResult->collected_date?->format('Y-m-d') ?? 'N/A'],
            ['is_completed', $testResult->is_completed ? '1' : '0'],
            ['is_reviewed', $testResult->is_reviewed ? '1' : '0'],
            ['Active ai_reviews rows', $activeReviews->isEmpty()
                ? 'none'
                : $activeReviews->map(fn ($r) => "id={$r->id} ({$r->processing_status})")->implode(', ')],
            ['Enrolled in consult call', $alreadyEnrolled ? 'YES' : 'NO'],
        ]);

        if (! $testResult->is_completed) {
            $this->warn('Warning: is_completed=0. The scheduled AI dispatch only picks up completed results, so no new AI review will be generated until the record is completed.');
        }

        if ($dryRun) {
            $this->info('DRY RUN - no changes made.');
            $this->line('Would: soft-delete '.$activeReviews->count().' ai_reviews row(s), set is_reviewed=0, '
                .($alreadyEnrolled ? 'skip consult-call check (already enrolled).' : 'run consult-call eligibility check.'));

            Log::channel('ai-command')->info('RerunTestResultReview: dry run completed', [
                'test_result_id' => $testResult->id,
                'ai_reviews_to_delete' => $activeReviews->pluck('id')->all(),
                'already_enrolled' => $alreadyEnrolled,
            ]);

            return self::SUCCESS;
        }

        if (! $force && ! $this->confirm("Soft-delete AI review and reset is_reviewed=0 for test result {$testResult->id}?")) {
            $this->info('Operation cancelled.');
            Log::channel('ai-command')->info('RerunTestResultReview: cancelled by user', [
                'test_result_id' => $testResult->id,
            ]);

            return self::SUCCESS;
        }

        // Step 1: soft-delete AI review and reset is_reviewed so the scheduled
        // ai:dispatch-unreviewed-async run picks the record up on its next turn.
        try {
            DB::beginTransaction();

            $deletedCount = AIReview::where('test_result_id', $testResult->id)->delete();

            $testResult->is_reviewed = false;
            $testResult->save();

            DB::commit();

            $this->info("AI review reset: {$deletedCount} ai_reviews row(s) soft-deleted, is_reviewed=0. The next scheduled ai:dispatch-unreviewed-async run will regenerate the review.");

            Log::channel('ai-command')->info('RerunTestResultReview: AI review soft-deleted and is_reviewed reset', [
                'test_result_id' => $testResult->id,
                'lab_no' => $testResult->lab_no,
                'soft_deleted_ai_review_ids' => $activeReviews->pluck('id')->all(),
            ]);
        } catch (Throwable $e) {
            DB::rollBack();

            $this->error('Failed to reset AI review: '.$e->getMessage());

            Log::channel('ai-command')->error('RerunTestResultReview: failed to reset AI review', [
                'test_result_id' => $testResult->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return self::FAILURE;
        }

        // Step 2: consult-call eligibility, only when not already enrolled.
        if ($alreadyEnrolled) {
            $this->info('Consult call: already enrolled, eligibility check skipped.');

            Log::channel('ai-command')->info('RerunTestResultReview: consult call already enrolled, skipped', [
                'test_result_id' => $testResult->id,
            ]);
        } else {
            $this->line('Consult call: not enrolled, running eligibility check...');

            // Swallows and logs its own errors; outcome is read back below.
            $dispatcher->checkConsultCallEligibility($testResult);

            $detail = ConsultCallDetails::where('test_result_id', $testResult->id)
                ->orderByDesc('id')
                ->first();

            if ($detail) {
                $this->info("Consult call: ENROLLED (consult_call_id={$detail->consult_call_id}, clinical_condition_id={$detail->clinical_condition_id}).");

                Log::channel('ai-command')->info('RerunTestResultReview: consult call enrolled', [
                    'test_result_id' => $testResult->id,
                    'consult_call_id' => $detail->consult_call_id,
                    'clinical_condition_id' => $detail->clinical_condition_id,
                ]);
            } else {
                $this->warn('Consult call: not enrolled (outside date window, not an eligible outlet, incomplete panels, no condition matched, or check failed - see laravel.log).');

                Log::channel('ai-command')->info('RerunTestResultReview: consult call not enrolled after eligibility check', [
                    'test_result_id' => $testResult->id,
                ]);
            }
        }

        Log::channel('ai-command')->info('RerunTestResultReview: completed', [
            'test_result_id' => $testResult->id,
        ]);

        return self::SUCCESS;
    }

    private function resolveById(int $testResultId): ?TestResult
    {
        $testResult = TestResult::find($testResultId);

        if (! $testResult) {
            $this->error("Test result not found for --test_result_id={$testResultId}");
            Log::channel('ai-command')->warning('RerunTestResultReview: test result not found', [
                'test_result_id' => $testResultId,
            ]);
        }

        return $testResult;
    }

    /**
     * Refuses an ambiguous lab_no so the wrong record is never reset.
     */
    private function resolveByLabNo(string $labNo): ?TestResult
    {
        $matches = TestResult::where('lab_no', $labNo)->get();

        if ($matches->isEmpty()) {
            $this->error("Test result not found for --lab_no={$labNo}");
            Log::channel('ai-command')->warning('RerunTestResultReview: test result not found', [
                'lab_no' => $labNo,
            ]);

            return null;
        }

        if ($matches->count() > 1) {
            $this->error("--lab_no={$labNo} matches {$matches->count()} test results. Re-run with --test_result_id instead.");
            $this->table(['ID', 'Collected Date', 'is_completed', 'is_reviewed'], $matches->map(fn ($tr) => [
                $tr->id,
                $tr->collected_date?->format('Y-m-d') ?? 'N/A',
                $tr->is_completed ? '1' : '0',
                $tr->is_reviewed ? '1' : '0',
            ]));

            Log::channel('ai-command')->warning('RerunTestResultReview: ambiguous lab_no', [
                'lab_no' => $labNo,
                'test_result_ids' => $matches->pluck('id')->all(),
            ]);

            return null;
        }

        return $matches->first();
    }

    private function isEnrolled(TestResult $testResult): bool
    {
        return ConsultCallDetails::where('test_result_id', $testResult->id)->exists();
    }
}
