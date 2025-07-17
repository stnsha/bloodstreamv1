<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use HasFactory, SoftDeletes;

    const nric = 'NRIC';
    const others = 'OTHERS';

    const female = 'F';
    const male = 'M';

    protected $fillable = ['icno', 'ic_type', 'name', 'dob', 'age', 'gender', 'tel'];

    protected $attributes = [
        'ic_type' => 'OTHERS',
        'name' => null,
        'dob' => null,
        'age' => null,
        'gender' => null,
        'tel' => null,
    ];
}
