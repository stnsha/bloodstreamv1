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
        'code',
        'name',
        'decimal_point',
        'unit',
        'sequence',
        'result_type',
        'identifier',
    ];

    protected $attributes = [
        'code' => null,
        'decimal_point' => null,
        'unit' => null,
        'sequence' => null,
        'result_type' => null,
        'identifier' => null,
    ];

    public function panels(): BelongsToMany
    {
        return $this->belongsToMany(Panel::class, 'panel_panel_items');
    }

    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }
}