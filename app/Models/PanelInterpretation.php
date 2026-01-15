<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PanelInterpretation extends Model
{
    use HasFactory;

    protected $fillable = [
        'panel_panel_item_id',
        'range',
        'interpretation',
    ];

    /**
     * Attribute casting.
     */
    protected $casts = [
        'panel_panel_item_id' => 'integer',
        'range' => 'string',
        'interpretation' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Default attribute values.
     */
    protected $attributes = [
        'range' => null,
    ];

    /**
     * Relationships
     */
    public function panelItem()
    {
        return $this->belongsTo(PanelPanelItem::class, 'panel_panel_item_id');
    }
}
