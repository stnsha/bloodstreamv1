<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Doctor extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'lab_id',
        'name',
        'code',
        'type',
        'outlet_name',
        'outlet_address',
        'outlet_phone'
    ];

    protected $attributes = [
        'code' => null,
        'type' => null,
        'outlet_name' => null,
        'outlet_address' => null,
        'outlet_phone' => null,
    ];
}
