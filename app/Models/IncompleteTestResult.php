<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncompleteTestResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'test_result_id',
        'expected_panel_count',
        'actual_panel_count',
    ];

    protected $casts = [
        'test_result_id' => 'integer',
        'expected_panel_count' => 'integer',
        'actual_panel_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function testResult(): BelongsTo
    {
        return $this->belongsTo(TestResult::class);
    }
}
