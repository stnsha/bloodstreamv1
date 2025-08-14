<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Panel extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['lab_id', 'panel_category_id', 'name', 'code', 'int_code', 'sequence', 'overall_notes'];

    protected $attributes = [
        'panel_category_id' => null,
        'int_code' => null,
        'sequence' => null,
        'overall_notes' => null,
    ];

    public function panelItems(): BelongsToMany
    {
        return $this->belongsToMany(PanelItem::class, 'panel_panel_items');
    }

    // Keep the old relationship for backward compatibility during migration
    public function legacyPanelItems(): HasMany
    {
        return $this->hasMany(PanelItem::class, 'panel_id', 'id');
    }
}