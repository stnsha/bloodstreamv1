<?php

namespace App\Services;

use App\Jobs\SendToAIServer;
use App\Models\MyHealthFeedDelivery;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Records MyHealth feed (Insyte) deliveries in myhealth_feed_deliveries.
 *
 * Tracking is non-critical: a failure to record is logged and never stops or
 * fails the feed itself.
 */
class MyHealthFeedDeliveryService
{
    /**
     * MyHealth feed switch (MH_KEY_FEED_ENABLED). False: no feed is sent by any
     * automatic path, and from MYHEALTH_FEED_FROM results fall back to the AI review.
     */
    public static function isEnabled(): bool
    {
        return (bool) config('services.myhealth_feed.enabled', true);
    }

    /**
     * AI review switch (AI_REVIEW_ENABLED). False: no result is sent to the AI
     * server; with the feed enabled the feed replaces the AI review right away.
     */
    public static function isAiReviewEnabled(): bool
    {
        return (bool) config('services.ai_review.enabled', true);
    }

    /**
     * True when the feed replaces the AI review and owns is_reviewed: feed enabled,
     * and the AI review is disabled or SendToAIServer::MYHEALTH_FEED_FROM is reached.
     * Otherwise every delivery is a testing copy that must not set is_reviewed.
     *
     * feed on  + AI on  -> both (copy) before MYHEALTH_FEED_FROM, feed only from then
     * feed on  + AI off -> feed only (live)
     * feed off + AI on  -> AI review only
     * feed off + AI off -> nothing is sent
     */
    public static function isLive(): bool
    {
        return self::isEnabled()
            && (! self::isAiReviewEnabled()
                || now()->gte(Carbon::parse(SendToAIServer::MYHEALTH_FEED_FROM)->startOfDay()));
    }

    /**
     * True during the testing overlap (MYHEALTH_FEED_TEST_FROM until MYHEALTH_FEED_FROM)
     * while both the feed and the AI review are enabled.
     */
    public static function isTesting(): bool
    {
        return self::isEnabled()
            && self::isAiReviewEnabled()
            && now()->gte(Carbon::parse(SendToAIServer::MYHEALTH_FEED_TEST_FROM)->startOfDay())
            && ! self::isLive();
    }

    /**
     * Start of an attempt: status PENDING, attempts + 1.
     */
    public function start(int $testResultId): void
    {
        $this->write($testResultId, 'start', function () use ($testResultId) {
            $delivery = MyHealthFeedDelivery::firstOrNew(['test_result_id' => $testResultId]);
            $delivery->status = MyHealthFeedDelivery::STATUS_PENDING;
            $delivery->attempts = ($delivery->attempts ?? 0) + 1;
            $delivery->save();
        });
    }

    public function sent(int $testResultId, int $httpStatus, array $payload): void
    {
        $this->write($testResultId, 'sent', fn () => MyHealthFeedDelivery::updateOrCreate(
            ['test_result_id' => $testResultId],
            [
                'status' => MyHealthFeedDelivery::STATUS_SENT,
                'http_status' => $httpStatus,
                'payload' => $payload,
                'sent_at' => now(),
            ]
        ));
    }

    public function failed(int $testResultId, ?int $httpStatus, ?array $payload): void
    {
        $values = [
            'status' => MyHealthFeedDelivery::STATUS_FAILED,
            'http_status' => $httpStatus,
        ];

        if ($payload !== null) {
            $values['payload'] = $payload;
        }

        $this->write($testResultId, 'failed', fn () => MyHealthFeedDelivery::updateOrCreate(
            ['test_result_id' => $testResultId],
            $values
        ));
    }

    /**
     * The feed cannot be built for this result (e.g. patient without IC);
     * retrying cannot fix it, so the retry commands leave SKIPPED alone.
     */
    public function skipped(int $testResultId): void
    {
        $this->write($testResultId, 'skipped', fn () => MyHealthFeedDelivery::updateOrCreate(
            ['test_result_id' => $testResultId],
            ['status' => MyHealthFeedDelivery::STATUS_SKIPPED]
        ));
    }

    /**
     * After the job gave up: a row still PENDING becomes FAILED so the retry
     * command picks it up.
     */
    public function failedFinal(int $testResultId): void
    {
        $this->write($testResultId, 'failed_final', fn () => MyHealthFeedDelivery::where('test_result_id', $testResultId)
            ->where('status', MyHealthFeedDelivery::STATUS_PENDING)
            ->update(['status' => MyHealthFeedDelivery::STATUS_FAILED]));
    }

    protected function write(int $testResultId, string $action, callable $callback): void
    {
        try {
            DB::beginTransaction();

            $callback();

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            Log::channel('myhealth-feed')->error('MyHealthFeedDeliveryService: failed to record delivery', [
                'test_result_id' => $testResultId,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        Log::channel('myhealth-feed')->info('MyHealthFeedDeliveryService: delivery recorded', [
            'test_result_id' => $testResultId,
            'action' => $action,
        ]);
    }
}
