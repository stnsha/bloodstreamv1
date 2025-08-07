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
}
