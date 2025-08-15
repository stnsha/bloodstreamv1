<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TestResultProfile extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'test_result_id',
        'panel_profile_id',
    ];
}