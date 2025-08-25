<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DoctorReview extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'test_result_id',
        'compiled_results',
        'review',
        'is_sync'
    ];

    protected $casts = [
        'test_result_id' => 'integer',
        'compiled_results' => 'array',
        'is_sync' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'is_sync' => false,
    ];

    public function testResult(): BelongsTo
    {
        return $this->belongsTo(TestResult::class);
    }
}
