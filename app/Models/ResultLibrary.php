<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResultLibrary extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'column_name',
        'code',
        'description',
    ];
}
