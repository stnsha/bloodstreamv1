<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsultCallFlag extends Model
{
    use HasFactory;

    protected $table = 'consult_call_flags';

    protected $fillable = [
        'test_result_id',
        'condition_id',
        'condition_description',
        'api_sent',
        'api_sent_at',
        'api_response',
    ];

    protected $casts = [
        'test_result_id' => 'integer',
        'condition_id' => 'integer',
        'api_sent' => 'boolean',
        'api_sent_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'condition_id' => 0,
        'condition_description' => null,
        'api_sent' => false,
        'api_sent_at' => null,
        'api_response' => null,
    ];

    public function testResult(): BelongsTo
    {
        return $this->belongsTo(TestResult::class, 'test_result_id', 'id');
    }
}
