<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PanelCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'panel_profile_id',
        'name',
        'code'
    ];

    protected $attributes = [
        'code' => null,
    ];
}
