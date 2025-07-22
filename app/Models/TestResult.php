<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TestResult extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'doctor_id',
        'patient_id',
        'ref_id',
        'bill_code',
        'lab_no',
        'collected_date',
        'received_date',
        'reported_date',
        'is_completed',
        'validated_by',
    ];

    protected $attributes = [
        'ref_id' => null,
        'bill_code' => null,
        'collected_date' => null,
        'received_date' => null,
        'reported_date' => null,
        'validated_by' => null,
        'is_completed' => false,
    ];
}
