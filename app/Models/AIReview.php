<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AIReview extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ai_reviews';

    protected $fillable = [
        'test_result_id',
        'processing_status',
        'compiled_results',
        'http_status',
        'ai_response',
        'raw_response',
    ];

    protected $casts = [
        'test_result_id' => 'integer',
        'processing_status' => 'string',
        'compiled_results' => 'array',
        'http_status' => 'integer',
        'ai_response' => 'array',
        'raw_response' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function testResult(): BelongsTo
    {
        return $this->belongsTo(TestResult::class, 'test_result_id', 'id');
    }

    // protected static function booted()
    // {
    //     static::updated(function ($model) {
    //         AuditTrail::create([
    //             'table_name' => $model->getTable(),
    //             'record_id' => $model->id,
    //             'action' => 'updated',
    //             'before' => $model->getOriginal(),
    //             'after' => $model->getDirty(),
    //         ]);
    //     });
    // }
}