<?php

namespace App\Console\Commands;

use App\Models\AIReview;
use App\Models\TestResult;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ResetReviewedAndPurgeAIReviews extends Command
{
    protected $signature = 'test-results:reset-reviewed-purge-ai-reviews
                            {--lab-id= : Filter by doctors.lab_id (required)}
                            {--year= : Filter by collected_date year (required)}
                            {--dry-run : Preview affected records without making any changes}';

    protected $description = 'Reset is_reviewed=false and permanently delete soft-deleted ai_reviews for completed+reviewed test results (matched by lab_id and collected_date year) that have no live AI review';

    public function handle(): int
    {
        $labId  = $this->option('lab-id');
        $year   = $this->option('year');
        $dryRun = $this->option('dry-run');

        if (!$labId || !$year) {
            $this->error('Both --lab-id and --year are required.');
            return Command::FAILURE;
        }

        Log::channel('ai-command')->info('ResetReviewedAndPurgeAIReviews started', [
            'lab_id'  => $labId,
            'year'    => $year,
            'dry_run' => $dryRun,
        ]);

        $this->info('Querying affected test results...');

        $query = TestResult::query()
            ->where('is_completed', true)
            ->where('is_reviewed', true)
            ->whereHas('doctor', fn ($q) => $q->where('lab_id', $labId))
            ->whereYear('collected_date', $year)
            ->whereDoesntHave('aiReview');

        $testResults = $query
            ->with('patient:id,icno')
            ->orderBy('collected_date', 'desc')
            ->get();

        $count = $testResults->count();

        if ($count === 0) {
            $this->info('No affected records found.');
            Log::channel('ai-command')->info('ResetReviewedAndPurgeAIReviews: no affected records found', [
                'lab_id' => $labId,
                'year'   => $year,
            ]);
            return Command::SUCCESS;
        }

        $this->info("Total matched: {$count} record(s).");
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
            Log::channel('ai-command')->info('ResetReviewedAndPurgeAIReviews: dry run completed', [
                'affected_count' => $count,
            ]);
            return Command::SUCCESS;
        }

        $this->line('');
        if (!$this->confirm("Proceed with resetting is_reviewed and purging soft-deleted ai_reviews for {$count} record(s)? This is irreversible.")) {
            $this->info('Operation cancelled.');
            Log::channel('ai-command')->info('ResetReviewedAndPurgeAIReviews: cancelled by user');
            return Command::SUCCESS;
        }

        $startTime = microtime(true);
        $processed = 0;
        $failed    = 0;
        $rows      = [];

        foreach ($testResults as $testResult) {
            try {
                DB::beginTransaction();

                $testResult->update(['is_reviewed' => false]);

                AIReview::onlyTrashed()
                    ->where('test_result_id', $testResult->id)
                    ->forceDelete();

                DB::commit();

                $processed++;
                $rows[] = [$testResult->id, 'OK', '-'];

                Log::channel('ai-command')->info('ResetReviewedAndPurgeAIReviews: record completed', [
                    'test_result_id' => $testResult->id,
                ]);
            } catch (Throwable $e) {
                DB::rollBack();

                $failed++;
                $rows[] = [$testResult->id, 'FAILED', $e->getMessage()];

                Log::channel('ai-command')->error('ResetReviewedAndPurgeAIReviews: record failed', [
                    'test_result_id' => $testResult->id,
                    'error'          => $e->getMessage(),
                    'file'           => $e->getFile(),
                    'line'           => $e->getLine(),
                ]);
            }
        }

        $duration = round(microtime(true) - $startTime, 2);

        $this->line('');
        $this->table(['ID', 'Status', 'Error'], $rows);
        $this->line('');
        $this->info("Done. Processed: {$processed}, Failed: {$failed}, Duration: {$duration}s.");

        Log::channel('ai-command')->info('ResetReviewedAndPurgeAIReviews completed', [
            'processed'        => $processed,
            'failed'           => $failed,
            'duration_seconds' => $duration,
        ]);

        return $failed > 0 && $processed === 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
