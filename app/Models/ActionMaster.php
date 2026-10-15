<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActionMaster extends Model
{
    protected $table = 'action_master';

    protected $fillable = [
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
