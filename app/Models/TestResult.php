<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'is_tagon',
        'collected_date',
        'received_date',
        'reported_date',
        'is_completed',
        'validated_by',
    ];

    protected $attributes = [
        'ref_id' => null,
        'bill_code' => null,
        'is_tagon' => false,
        'collected_date' => null,
        'received_date' => null,
        'reported_date' => null,
        'validated_by' => null,
        'is_completed' => false,
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'doctor_id', 'id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id', 'id');
    }

    public function testResultItems(): HasMany
    {
        return $this->hasMany(TestResultItem::class, 'test_result_id', 'id');
    }

    public function testResultProfiles(): HasMany
    {
        return $this->hasMany(TestResultProfile::class, 'test_result_id', 'id');
    }

    public function profiles()
    {
        return $this->hasManyThrough(
            PanelProfile::class,
            TestResultProfile::class,
            'test_result_id',
            'id',
            'id',
            'panel_profile_id'
        );
    }
}