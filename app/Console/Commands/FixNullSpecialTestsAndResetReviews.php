<?php

namespace App\Console\Commands;

use App\Models\AIReview;
use App\Models\TestResult;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class FixNullSpecialTestsAndResetReviews extends Command
{
    protected $signature = 'special-tests:fix-null-reviews
                            {--from= : Start of collected_date range (Y-m-d), defaults to 2026-06-01}
                            {--to=   : End of collected_date range (Y-m-d), defaults to 2026-06-30}
                            {--dry-run : Preview affected records without making any changes}';

    protected $description = 'Recalculate null special tests, reset is_reviewed=false, and soft-delete AI reviews for affected records in a collected_date range';

    public function handle(): int
    {
        $from = $this->option('from') ?? '2026-06-01';
        $to   = $this->option('to') ?? '2026-06-30';
        $dryRun = $this->option('dry-run');

        $fromDate = Carbon::parse($from)->startOfDay();
        $toDate   = Carbon::parse($to)->endOfDay();

        Log::channel('ai-command')->info('FixNullSpecialTestsAndResetReviews started', [
            'from'    => $fromDate->toDateTimeString(),
            'to'      => $toDate->toDateTimeString(),
            'dry_run' => $dryRun,
        ]);

        $this->info('Querying affected test results...');

        $testResults = TestResult::whereBetween('collected_date', [$fromDate, $toDate])
            ->where('is_completed', true)
            ->whereHas('testResultSpecialTests', fn ($q) => $q->whereNull('value'))
            ->with([
                'patient:id,icno',
                'testResultSpecialTests',
            ])
            ->orderBy('collected_date')
            ->get();

        $count = $testResults->count();

        if ($count === 0) {
            $this->info('No affected records found.');
            Log::channel('ai-command')->info('FixNullSpecialTestsAndResetReviews: no affected records found', [
                'from' => $fromDate->toDateString(),
                'to'   => $toDate->toDateString(),
            ]);
            return Command::SUCCESS;
        }

        $this->info("Found {$count} test result(s) with null special tests.");
        $this->line('');

        // Always show preview table
        $this->table(
            ['ID', 'Lab No', 'Collected Date', 'Patient IC', 'Null / Total Special Tests'],
            $testResults->map(function ($tr) {
                $specialTests = $tr->testResultSpecialTests;
                $nullCount    = $specialTests->whereNull('value')->count();
                $totalCount   = $specialTests->count();
                return [
                    $tr->id,
                    $tr->lab_no ?? 'N/A',
                    $tr->collected_date ? $tr->collected_date->format('Y-m-d') : 'N/A',
                    $tr->patient->icno ?? 'N/A',
                    "{$nullCount} / {$totalCount}",
                ];
            })
        );

        if ($dryRun) {
            $this->line('');
            $this->info('DRY RUN — no changes made.');
            Log::channel('ai-command')->info('FixNullSpecialTestsAndResetReviews: dry run completed', [
                'affected_count' => $count,
            ]);
            return Command::SUCCESS;
        }

        $this->line('');
        if (!$this->confirm("Proceed with fixing {$count} record(s)? This will recalculate special tests, set is_reviewed=false, and soft-delete AI reviews.")) {
            $this->info('Operation cancelled.');
            Log::channel('ai-command')->info('FixNullSpecialTestsAndResetReviews: cancelled by user');
            return Command::SUCCESS;
        }

        $startTime = microtime(true);
        $processed = 0;
        $failed    = 0;
        $rows      = [];

        foreach ($testResults as $testResult) {
            Log::channel('ai-command')->info('FixNullSpecialTestsAndResetReviews: processing record', [
                'test_result_id' => $testResult->id,
                'lab_no'         => $testResult->lab_no,
            ]);

            try {
                // Step A: Recalculate special tests (outside transaction — queries external DB)
                Artisan::call('special-tests:recalculate', ['ids' => (string) $testResult->id]);

                // Step B: Reset review state atomically
                DB::beginTransaction();

                $testResult->update(['is_reviewed' => false]);

                AIReview::where('test_result_id', $testResult->id)->delete();

                DB::commit();

                $processed++;
                $rows[] = [$testResult->id, 'OK', '-'];

                Log::channel('ai-command')->info('FixNullSpecialTestsAndResetReviews: record completed', [
                    'test_result_id' => $testResult->id,
                ]);
            } catch (Throwable $e) {
                DB::rollBack();

                $failed++;
                $rows[] = [$testResult->id, 'FAILED', $e->getMessage()];

                Log::channel('ai-command')->error('FixNullSpecialTestsAndResetReviews: record failed', [
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

        if ($processed > 0) {
            $this->info("Affected records are now eligible for AI re-dispatch via ai:dispatch-unreviewed-async.");
        }

        Log::channel('ai-command')->info('FixNullSpecialTestsAndResetReviews completed', [
            'processed'       => $processed,
            'failed'          => $failed,
            'duration_seconds' => $duration,
        ]);

        return $failed > 0 && $processed === 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
