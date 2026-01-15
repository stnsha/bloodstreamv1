<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestResultSpecialTest extends Model
{
    use HasFactory;

    protected $fillable = [
        'test_result_id',
        'panel_panel_item_id',
        'value',
        'panel_interpretation_id',
    ];

    protected $casts = [
        'test_result_id' => 'integer',
        'panel_panel_item_id' => 'integer',
        'panel_interpretation_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'value' => null,
        'panel_interpretation_id' => null,
    ];

    public function testResult(): BelongsTo
    {
        return $this->belongsTo(TestResult::class, 'test_result_id', 'id');
    }

    public function panelPanelItem(): BelongsTo
    {
        return $this->belongsTo(PanelPanelItem::class, 'panel_panel_item_id', 'id');
    }

    public function panelInterpretation(): BelongsTo
    {
        return $this->belongsTo(PanelInterpretation::class, 'panel_interpretation_id', 'id');
    }
}
