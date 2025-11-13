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
        'compiled_results',
        'http_status',
        'ai_response',
        'error_message',
        'is_successful'
    ];

    protected $casts = [
        'test_result_id' => 'integer',
        'compiled_results' => 'array',
        'http_status' => 'integer',
        'ai_response' => 'array',
        'is_successful' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function testResult(): BelongsTo
    {
        return $this->belongsTo(TestResult::class, 'test_result_id', 'id');
    }
}