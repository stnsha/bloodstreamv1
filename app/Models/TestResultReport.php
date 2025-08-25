<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestResultReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'test_result_id',
        'panel_id',
        'text',
        'is_completed',
    ];

    protected $casts = [
        'test_result_id' => 'integer',
        'panel_id' => 'integer',
        'is_completed' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'is_completed' => false,
    ];

    public function testResult(): BelongsTo
    {
        return $this->belongsTo(TestResult::class);
    }

    public function panel(): BelongsTo
    {
        return $this->belongsTo(Panel::class);
    }
}
