<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PanelTag extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'lab_id',
        'panel_id',
        'name',
        'code',
    ];

    protected $attributes = [
        'panel_id' => null,
    ];
}
