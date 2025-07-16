<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TestResultReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'test_result_id',
        'panel_id',
        'text',
        'is_completed',
    ];
}
