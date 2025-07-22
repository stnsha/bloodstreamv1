<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PanelItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'panel_id',
        'panel_tag_id',
        'name',
        'decimal_point',
        'unit',
        'sequence',
        'result_type',
    ];

    protected $attributes = [
        'panel_tag_id' => null,
        'decimal_point' => null,
        'unit' => null,
        'sequence' => null,
        'result_type' => null,
    ];
}
