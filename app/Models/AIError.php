<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIError extends Model
{
    use HasFactory;

    protected $table = 'ai_errors';

    protected $fillable = [
        'test_result_id',
        'processing_status',
        'http_status',
        'error_message',
        'compiled_data',
        'attempt_count',
    ];

    protected $casts = [
        'test_result_id' => 'integer',
        'processing_status' => 'string',
        'http_status' => 'integer',
        'compiled_data' => 'array',
        'attempt_count' => 'integer',
    ];

    public function testResult(): BelongsTo
    {
        return $this->belongsTo(TestResult::class, 'test_result_id', 'id');
    }
}