<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TestResultItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'test_result_id',
        'panel_panel_item_id',
        'is_tagon',
        'reference_range_id',
        'value',
        'flag',
        'test_notes',
        'status',
        'is_completed'
    ];

    protected $attributes = [
        'is_tagon' => false,
        'reference_range_id' => null,
        'value' => null,
        'flag' => null,
        'test_notes' => null,
        'status' => null,
        'is_completed' => false,
    ];

    public function testResult(): BelongsTo
    {
        return $this->belongsTo(TestResult::class, 'test_result_id', 'id');
    }

    public function panelPanelItem(): BelongsTo
    {
        return $this->belongsTo(PanelPanelItem::class, 'panel_panel_item_id', 'id');
    }

    public function panelItem()
    {
        return $this->hasOneThrough(
            PanelItem::class,
            PanelPanelItem::class,
            'id',
            'id',
            'panel_panel_item_id',
            'panel_item_id'
        );
    }

    public function panel()
    {
        return $this->hasOneThrough(
            Panel::class,
            PanelPanelItem::class,
            'id',
            'id',
            'panel_panel_item_id',
            'panel_id'
        );
    }

    public function referenceRange(): BelongsTo
    {
        return $this->belongsTo(ReferenceRange::class, 'reference_range_id', 'id');
    }
}