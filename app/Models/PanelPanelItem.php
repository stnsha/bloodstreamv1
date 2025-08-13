<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}