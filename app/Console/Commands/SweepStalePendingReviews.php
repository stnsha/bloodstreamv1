<?php

namespace App\Console\Commands;

use App\Models\AIError;
use App\Models\AIReview;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SweepStalePendingReviews extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:sweep-stale-pending {--dry-run : Preview affected records without making any changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Give up on ai_reviews rows stuck PENDING with no webhook response past the staleness threshold - mark SUPERSEDED and record in ai_errors so the scheduled retry commands pick them back up';

    /**
     * How long a PENDING row is given to receive a webhook before it's
     * considered stuck. SendToAIServer only flips a row to SUPERSEDED when the
     * send itself fails - if the AI server accepts the request and then never
     * calls the webhook back, nothing else ever notices, so this sweep is the
     * only thing watching the clock for that case.
     *
     * @var int
     */
    protected const STALE_MINUTES = 10;

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $staleThreshold = now()->subMinutes(self::STALE_MINUTES);

        Log::channel('ai-command')->info('SweepStalePendingReviews: started', [
            'stale_minutes' => self::STALE_MINUTES,
            'dry_run' => $dryRun,
        ]);

        try {
            $staleReviews = AIReview::where('processing_status', 'PENDING')
                ->where('updated_at', '<', $staleThreshold)
                ->get();

            if ($staleReviews->isEmpty()) {
                $this->info('No stale PENDING reviews found.');
                Log::channel('ai-command')->info('SweepStalePendingReviews: no stale records found');

                return self::SUCCESS;
            }

            $this->info("Found {$staleReviews->count()} stale PENDING review(s) (no webhook in over ".self::STALE_MINUTES.' minutes).');

            if ($dryRun) {
                $this->table(
                    ['AIReview ID', 'Test Result ID', 'Last Updated'],
                    $staleReviews->map(fn ($r) => [$r->id, $r->test_result_id, $r->updated_at])
                );

                $this->info('DRY RUN - no changes made.');
                Log::channel('ai-command')->info('SweepStalePendingReviews: dry run completed', [
                    'stale_count' => $staleReviews->count(),
                ]);

                return self::SUCCESS;
            }

            $sweptCount = 0;

            foreach ($staleReviews as $review) {
                try {
                    DB::transaction(function () use ($review) {
                        $review->update(['processing_status' => 'SUPERSEDED']);

                        AIError::create([
                            'test_result_id' => $review->test_result_id,
                            'processing_status' => 'FAILED',
                            'error_message' => 'No webhook response received within '.self::STALE_MINUTES.' minutes',
                            'compiled_data' => $review->compiled_results,
                            'attempt_count' => 1,
                        ]);
                    });

                    $sweptCount++;

                    Log::channel('ai-command')->warning('SweepStalePendingReviews: marked stale review SUPERSEDED', [
                        'test_result_id' => $review->test_result_id,
                        'ai_review_id' => $review->id,
                        'pending_since' => $review->updated_at,
                    ]);
                } catch (Throwable $e) {
                    Log::channel('ai-command')->error('SweepStalePendingReviews: failed to sweep record', [
                        'test_result_id' => $review->test_result_id,
                        'ai_review_id' => $review->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->info("Swept {$sweptCount} of {$staleReviews->count()} stale review(s).");

            Log::channel('ai-command')->info('SweepStalePendingReviews: completed', [
                'total_found' => $staleReviews->count(),
                'swept' => $sweptCount,
            ]);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Command failed: {$e->getMessage()}");
            Log::channel('ai-command')->error('SweepStalePendingReviews: command failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ]);

            return self::FAILURE;
        }
    }
}
