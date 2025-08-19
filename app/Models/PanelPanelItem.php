<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PanelPanelItem extends Model
{
    use HasFactory;

    protected $table = 'panel_panel_items';

    protected $fillable = [
        'panel_id',
        'panel_item_id',
    ];

    public function panel(): BelongsTo
    {
        return $this->belongsTo(Panel::class);
    }

    public function panelItem(): BelongsTo
    {
        return $this->belongsTo(PanelItem::class);
    }

    public function referenceRanges(): HasMany
    {
        return $this->hasMany(ReferenceRange::class, 'panel_panel_item_id', 'id');
    }

    public function testResultItems(): HasMany
    {
        return $this->hasMany(TestResultItem::class, 'panel_panel_item_id', 'id');
    }
}