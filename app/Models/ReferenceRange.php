<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReferenceRange extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['panel_item_id', 'value'];

    protected $attributes = [
        'value' => null,
    ];

    public function panelItem(): BelongsTo
    {
        return $this->belongsTo(PanelItem::class);
    }
}
