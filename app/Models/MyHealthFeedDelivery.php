<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Delivery of a test result to the MyHealth lab feed (Insyte). One row per
 * test result; updated on every attempt by SendToMyHealthFeed.
 */
class MyHealthFeedDelivery extends Model
{
    const STATUS_PENDING = 'PENDING';

    const STATUS_SENT = 'SENT';

    const STATUS_FAILED = 'FAILED';

    const STATUS_SKIPPED = 'SKIPPED';

    protected $table = 'myhealth_feed_deliveries';

    protected $fillable = [
        'test_result_id',
        'status',
        'http_status',
        'attempts',
        'payload',
        'sent_at',
    ];

    protected $casts = [
        'test_result_id' => 'integer',
        'http_status' => 'integer',
        'attempts' => 'integer',
        'payload' => 'array',
        'sent_at' => 'datetime',
    ];

    public function testResult(): BelongsTo
    {
        return $this->belongsTo(TestResult::class, 'test_result_id', 'id');
    }
}
