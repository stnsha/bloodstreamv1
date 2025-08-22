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
        'lab_no',
        'collected_date',
        'received_date',
        'reported_date',
        'validated_by',
        'is_completed',
    ];

    protected $casts = [
        'doctor_id' => 'integer',
        'patient_id' => 'integer',
        'is_completed' => 'boolean',
        'collected_date' => 'datetime',
        'received_date' => 'datetime',
        'reported_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
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