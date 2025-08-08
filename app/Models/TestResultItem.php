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
        'panel_item_id',
        'reference_range_id',
        'value',
        'flag',
        'test_notes',
        'status',
        'is_completed'
    ];

    protected $attributes = [
        'reference_range_id' => null,
        'value' => null,
        'flag' => null,
        'test_notes' => null,
        'status' => null,
        'is_completed' => false,
    ];
}
