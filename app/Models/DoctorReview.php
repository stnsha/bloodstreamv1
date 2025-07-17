<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DoctorReview extends Model
{
    use HasFactory, SoftDeletes;

    protected $casts = [
        'compiled_results' => 'array'
    ];

    protected $fillable = [
        'test_result_id',
        'compiled_results',
        'review',
        'is_sync'
    ];

    protected $attributes = [
        'is_sync' => false,
    ];
}
