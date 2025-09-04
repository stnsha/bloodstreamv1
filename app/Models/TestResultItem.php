<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TestResultItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'test_result_id',
        'panel_panel_item_id',
        'reference_range_id',
        'value',
        'flag',
        'sequence',
        'is_tagon',
        'has_amended',
    ];

    protected $casts = [
        'test_result_id' => 'integer',
        'panel_panel_item_id' => 'integer',
        'reference_range_id' => 'integer',
        'sequence' => 'integer',
        'is_tagon' => 'boolean',
        'has_amended' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'reference_range_id' => null,
        'value' => null,
        'flag' => null,
        'is_tagon' => false,
        'has_amended' => false,
    ];

    public function testResult(): BelongsTo
    {
        return $this->belongsTo(TestResult::class, 'test_result_id', 'id');
    }

    public function panelPanelItem(): BelongsTo
    {
        return $this->belongsTo(PanelPanelItem::class, 'panel_panel_item_id', 'id');
    }

    public function panelComments(): BelongsToMany
    {
        return $this->belongsToMany(PanelComment::class, 'test_result_item_panel_comments')
                    ->withTimestamps();
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