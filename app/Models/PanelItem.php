<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PanelItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'lab_id',
        'master_panel_item_id',
        'name',
        'identifier',
        'unit',
    ];

    protected $casts = [
        'lab_id' => 'integer',
        'master_panel_item_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'master_panel_item_id' => null,
        'identifier' => null,
        'name' => null,
        'unit' => null,
    ];

    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }

    public function masterPanelItem(): BelongsTo
    {
        return $this->belongsTo(MasterPanelItem::class);
    }

    public function panels(): BelongsToMany
    {
        return $this->belongsToMany(Panel::class, 'panel_panel_items')
            ->withTimestamps();
    }
}