<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TestResultItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'test_result_id',
        'reference_range_id',
        'value',
        'flag',
        'test_notes',
        'status',
        'is_completed'
    ];
}
