<?php

namespace App\Console\Commands;

use App\Jobs\SendToMyHealthFeed;
use App\Models\MyHealthFeedDelivery;
use App\Services\MyHealthFeedDeliveryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Resend MyHealth feed deliveries that FAILED (rejected, connection failure, or
 * a PENDING attempt swept as stale). Like ai:retry-failed-reviews there is no
 * attempt cap: every run retries until the delivery is SENT. SKIPPED deliveries
 * (feed cannot be built) are never retried.
 *
 * Before MYHEALTH_FEED_FROM the resend is a testing copy (is_reviewed untouched);
 * from then on it is live and sets is_reviewed on success, skipping results that
 * are already reviewed.
 */
class RetryFailedMyHealthFeeds extends Command
{
    protected $signature = 'myhealth:retry-failed-feeds
        {--hours=12 : Retry deliveries that failed within this many hours}
        {--limit=50 : Maximum deliveries to retry}
        {--dry-run : List the deliveries without dispatching}';

    protected $description = 'Resend FAILED MyHealth feed deliveries - no attempt cap, keeps retrying until SENT';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');
        $live = MyHealthFeedDeliveryService::isLive();

        Log::channel('myhealth-feed')->info('myhealth:retry-failed-feeds: started', [
            'hours' => $hours,
            'limit' => $limit,
            'live' => $live,
            'dry_run' => $dryRun,
        ]);

        try {
            $deliveries = MyHealthFeedDelivery::with('testResult:id,is_reviewed')
                ->where('status', MyHealthFeedDelivery::STATUS_FAILED)
                ->where('updated_at', '>=', now()->subHours($hours))
                ->orderBy('updated_at')
                ->limit($limit)
                ->get();
        } catch (Throwable $e) {
            $this->error('Query failed: '.$e->getMessage());
            Log::channel('myhealth-feed')->error('myhealth:retry-failed-feeds: query failed', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }

        if ($deliveries->isEmpty()) {
            $this->info('No FAILED MyHealth feed deliveries to retry.');
            Log::channel('myhealth-feed')->info('myhealth:retry-failed-feeds: nothing to retry');

            return self::SUCCESS;
        }

        $this->info("Found {$deliveries->count()} FAILED deliver(ies).");

        if ($dryRun) {
            $this->table(
                ['Test Result ID', 'HTTP', 'Attempts', 'Last Attempt'],
                $deliveries->map(fn ($d) => [$d->test_result_id, $d->http_status ?? '-', $d->attempts, $d->updated_at])
            );
            $this->info('DRY RUN - nothing dispatched.');

            return self::SUCCESS;
        }

        $dispatched = 0;
        $skipped = 0;

        foreach ($deliveries as $delivery) {
            if (! $delivery->testResult || ($live && $delivery->testResult->is_reviewed)) {
                $skipped++;

                continue;
            }

            try {
                SendToMyHealthFeed::dispatch($delivery->test_result_id, $live);
                $dispatched++;
            } catch (Throwable $e) {
                Log::channel('myhealth-feed')->error('myhealth:retry-failed-feeds: dispatch failed', [
                    'test_result_id' => $delivery->test_result_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Dispatched {$dispatched}, skipped {$skipped} (missing or already reviewed).");
        Log::channel('myhealth-feed')->info('myhealth:retry-failed-feeds: completed', [
            'found' => $deliveries->count(),
            'dispatched' => $dispatched,
            'skipped' => $skipped,
        ]);

        return self::SUCCESS;
    }
}
