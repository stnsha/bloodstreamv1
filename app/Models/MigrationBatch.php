<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MigrationBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_uuid',
        'total_reports',
        'processed',
        'success',
        'failed',
        'status',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'total_reports' => 'integer',
        'processed' => 'integer',
        'success' => 'integer',
        'failed' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_PARTIAL_FAILURE = 'partial_failure';

    public function items(): HasMany
    {
        return $this->hasMany(MigrationBatchItem::class, 'batch_id', 'id');
    }

    public function failedItems(): HasMany
    {
        return $this->hasMany(MigrationBatchItem::class, 'batch_id', 'id')
            ->where('status', MigrationBatchItem::STATUS_FAILED);
    }
}
