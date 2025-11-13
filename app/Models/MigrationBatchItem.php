<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MigrationBatchItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'ref_id',
        'test_result_id',
        'report_data',
        'status',
        'attempt_count',
        'error_message',
        'processed_at',
    ];

    protected $casts = [
        'batch_id' => 'integer',
        'attempt_count' => 'integer',
        'processed_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';
    const STATUS_SKIPPED = 'skipped';

    public function batch(): BelongsTo
    {
        return $this->belongsTo(MigrationBatch::class, 'batch_id', 'id');
    }
}
