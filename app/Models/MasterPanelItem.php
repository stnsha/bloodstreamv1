<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MasterPanelItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'chi_character',
        'unit',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'chi_character' => null,
        'unit' => null,
    ];

    public function panelItems(): HasMany
    {
        return $this->hasMany(PanelItem::class);
    }
}