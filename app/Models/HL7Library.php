<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HL7Library extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'hl7_libraries';

    protected $fillable = [
        'value',
        'description',
        'code',
    ];
}
