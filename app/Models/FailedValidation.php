<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FailedValidation extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_id',
        'lab_id',
        'lab_no',
        'reason',
        'missing_details',
        'payload',
    ];

    protected $casts = [
        'lab_id' => 'integer',
        'payload' => 'array',
    ];
}
