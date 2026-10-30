<?php

namespace App\Console\Commands;

use App\Models\MyHealthFeedDelivery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Give up on MyHealth feed deliveries stuck PENDING (the job was lost, e.g. the
 * worker died mid-send): mark them FAILED so myhealth:retry-failed-feeds sends
 * them again.
 */
class SweepStalePendingMyHealthFeeds extends Command
{
    protected $signature = 'myhealth:sweep-stale-pending {--dry-run : Preview affected records without making any changes}';

    protected $description = 'Mark MyHealth feed deliveries stuck PENDING as FAILED so the retry command picks them up';

    /**
     * SendToMyHealthFeed runs up to 3 attempts of 120s with 30/60/120s backoff
     * (about 9.5 minutes in total) and refreshes updated_at on every attempt, so
     * a row PENDING for longer than this has no job left working on it.
     */
    protected const STALE_MINUTES = 15;

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $staleThreshold = now()->subMinutes(self::STALE_MINUTES);

        Log::channel('myhealth-feed')->info('myhealth:sweep-stale-pending: started', [
            'stale_minutes' => self::STALE_MINUTES,
            'dry_run' => $dryRun,
        ]);

        try {
            $query = MyHealthFeedDelivery::where('status', MyHealthFeedDelivery::STATUS_PENDING)
                ->where('updated_at', '<', $staleThreshold);

            $staleCount = (clone $query)->count();

            if ($staleCount === 0) {
                $this->info('No stale PENDING MyHealth feed deliveries found.');
                Log::channel('myhealth-feed')->info('myhealth:sweep-stale-pending: nothing found');

                return self::SUCCESS;
            }

            if ($dryRun) {
                $this->info("Found {$staleCount} stale PENDING deliver(ies).");
                $this->line('Test result IDs: '.(clone $query)->pluck('test_result_id')->implode(', '));
                $this->info('DRY RUN - no changes made.');

                return self::SUCCESS;
            }

            DB::beginTransaction();

            $swept = $query->update(['status' => MyHealthFeedDelivery::STATUS_FAILED]);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            $this->error('Command failed: '.$e->getMessage());
            Log::channel('myhealth-feed')->error('myhealth:sweep-stale-pending: failed', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }

        $this->info("Marked {$swept} stale PENDING deliver(ies) FAILED.");
        Log::channel('myhealth-feed')->warning('myhealth:sweep-stale-pending: completed', [
            'swept' => $swept,
        ]);

        return self::SUCCESS;
    }
}
