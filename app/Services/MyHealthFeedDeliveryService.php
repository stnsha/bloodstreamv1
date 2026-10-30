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
     * True once the feed replaces the AI review (SendToAIServer::MYHEALTH_FEED_FROM).
     * Before that every delivery is a testing copy that must not set is_reviewed.
     */
    public static function isLive(): bool
    {
        return now()->gte(Carbon::parse(SendToAIServer::MYHEALTH_FEED_FROM)->startOfDay());
    }

    /**
     * True during the testing overlap (MYHEALTH_FEED_TEST_FROM until MYHEALTH_FEED_FROM).
     */
    public static function isTesting(): bool
    {
        return now()->gte(Carbon::parse(SendToAIServer::MYHEALTH_FEED_TEST_FROM)->startOfDay())
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
