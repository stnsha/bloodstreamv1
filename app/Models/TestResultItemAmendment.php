<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestResultItemAmendment extends Model
{
    protected $fillable = [
        'test_result_item_id',
        'test_result_id',
        'panel_panel_item_id',
        'reference_range_id',
        'value',
        'flag',
        'sequence',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function testResultItem(): BelongsTo
    {
        return $this->belongsTo(TestResultItem::class);
    }
}
