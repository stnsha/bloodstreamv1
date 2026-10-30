<?php

namespace App\Console\Commands;

use App\Models\MyHealthFeedDelivery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Clear the stored payload (2-year patient profile) of MyHealth feed deliveries
 * older than --days. The delivery row itself (status, HTTP status, attempts,
 * sent_at) is kept.
 */
class PurgeMyHealthFeedPayloads extends Command
{
    protected $signature = 'myhealth:purge-feed-payloads
        {--days=30 : Clear payloads of deliveries last updated more than this many days ago}
        {--chunk=500 : Rows cleared per batch}
        {--dry-run : Count the payloads without clearing them}';

    protected $description = 'Clear stored MyHealth feed payloads older than 30 days (rows are kept)';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $chunk = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subDays($days);

        Log::channel('myhealth-feed')->info('myhealth:purge-feed-payloads: started', [
            'days' => $days,
            'cutoff' => $cutoff->toDateTimeString(),
            'dry_run' => $dryRun,
        ]);

        $baseQuery = fn () => MyHealthFeedDelivery::whereNotNull('payload')
            ->where('updated_at', '<', $cutoff);

        try {
            $total = $baseQuery()->count();
        } catch (Throwable $e) {
            $this->error('Query failed: '.$e->getMessage());
            Log::channel('myhealth-feed')->error('myhealth:purge-feed-payloads: query failed', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }

        if ($total === 0) {
            $this->info('No payloads to clear.');
            Log::channel('myhealth-feed')->info('myhealth:purge-feed-payloads: nothing to clear');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info("DRY RUN - {$total} payload(s) older than {$days} days would be cleared.");

            return self::SUCCESS;
        }

        $cleared = 0;

        try {
            do {
                $ids = $baseQuery()->limit($chunk)->pluck('id');

                if ($ids->isEmpty()) {
                    break;
                }

                DB::beginTransaction();

                // Query builder update keeps updated_at unchanged
                $cleared += DB::table('myhealth_feed_deliveries')
                    ->whereIn('id', $ids)
                    ->update(['payload' => null]);

                DB::commit();
            } while ($ids->count() === $chunk);
        } catch (Throwable $e) {
            DB::rollBack();

            $this->error('Purge failed: '.$e->getMessage());
            Log::channel('myhealth-feed')->error('myhealth:purge-feed-payloads: failed', [
                'cleared_before_failure' => $cleared,
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }

        $this->info("Cleared {$cleared} payload(s).");
        Log::channel('myhealth-feed')->info('myhealth:purge-feed-payloads: completed', [
            'cleared' => $cleared,
        ]);

        return self::SUCCESS;
    }
}
