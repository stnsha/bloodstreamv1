<?php

namespace App\Console\Commands;

use App\Jobs\SendToAIServer;
use App\Jobs\SendToMyHealthFeed;
use App\Models\AIReview;
use App\Services\MyHealthFeedDeliveryService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Testing overlap (MYHEALTH_FEED_TEST_FROM until MYHEALTH_FEED_FROM): find
 * results whose AI review was accepted (ai_reviews PENDING or COMPLETED) but
 * that never got their MyHealth feed copy (no myhealth_feed_deliveries row),
 * and send the copy. ARR-enrolled results are excluded.
 *
 * From MYHEALTH_FEED_FROM the feed replaces the AI review and missed results are
 * already picked up by ai:dispatch-unreviewed-async / ai:reconcile-reviews, so
 * this command does nothing.
 */
class ReconcileMyHealthFeeds extends Command
{
    protected $signature = 'myhealth:reconcile-feeds
        {--hours=6 : Look back this many hours}
        {--limit=200 : Maximum results to dispatch}
        {--dry-run : List the results without dispatching}';

    protected $description = 'Send the MyHealth feed copy for results whose AI review was accepted but no feed was sent (testing overlap only)';

    /**
     * Leave fresh results to the normal flow (SendToAIServer dispatches the copy
     * right after the AI server accepts).
     */
    protected const GRACE_MINUTES = 5;

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');

        if (! MyHealthFeedDeliveryService::isTesting()) {
            $this->info('Not in the MyHealth feed testing period; nothing to reconcile.');
            Log::channel('myhealth-feed')->info('myhealth:reconcile-feeds: outside testing period, nothing to do');

            return self::SUCCESS;
        }

        $since = now()->subHours($hours);
        $testingStart = Carbon::parse(SendToAIServer::MYHEALTH_FEED_TEST_FROM)->startOfDay();

        if ($since->lt($testingStart)) {
            $since = $testingStart;
        }

        Log::channel('myhealth-feed')->info('myhealth:reconcile-feeds: started', [
            'since' => $since->toDateTimeString(),
            'limit' => $limit,
            'dry_run' => $dryRun,
        ]);

        try {
            $testResultIds = AIReview::whereIn('processing_status', ['PENDING', 'COMPLETED'])
                ->where('updated_at', '>=', $since)
                ->where('updated_at', '<', now()->subMinutes(self::GRACE_MINUTES))
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('myhealth_feed_deliveries')
                        ->whereColumn('myhealth_feed_deliveries.test_result_id', 'ai_reviews.test_result_id');
                })
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('arr_enrollments')
                        ->whereColumn('arr_enrollments.test_result_id', 'ai_reviews.test_result_id')
                        ->whereNull('arr_enrollments.deleted_at');
                })
                ->orderByDesc('updated_at')
                ->limit($limit)
                ->pluck('test_result_id');
        } catch (Throwable $e) {
            $this->error('Query failed: '.$e->getMessage());
            Log::channel('myhealth-feed')->error('myhealth:reconcile-feeds: query failed', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }

        if ($testResultIds->isEmpty()) {
            $this->info('No results missing a MyHealth feed copy.');
            Log::channel('myhealth-feed')->info('myhealth:reconcile-feeds: nothing to dispatch');

            return self::SUCCESS;
        }

        $this->info("Found {$testResultIds->count()} result(s) missing a MyHealth feed copy.");

        if ($dryRun) {
            $this->line('Test result IDs: '.$testResultIds->implode(', '));
            $this->info('DRY RUN - nothing dispatched.');

            return self::SUCCESS;
        }

        $dispatched = 0;

        foreach ($testResultIds as $testResultId) {
            try {
                SendToMyHealthFeed::dispatch((int) $testResultId, false);
                $dispatched++;
            } catch (Throwable $e) {
                Log::channel('myhealth-feed')->error('myhealth:reconcile-feeds: dispatch failed', [
                    'test_result_id' => $testResultId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Dispatched {$dispatched} of {$testResultIds->count()}.");
        Log::channel('myhealth-feed')->info('myhealth:reconcile-feeds: completed', [
            'found' => $testResultIds->count(),
            'dispatched' => $dispatched,
        ]);

        return self::SUCCESS;
    }
}
