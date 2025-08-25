<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReferenceRange extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['panel_panel_item_id', 'value'];

    protected $casts = [
        'panel_panel_item_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function panelPanelItem(): BelongsTo
    {
        return $this->belongsTo(PanelPanelItem::class, 'panel_panel_item_id', 'id');
    }
}