<?php

namespace App\Models;

use App\Models\Eurofins\ReportRecord;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        'is_reviewed',
        'manual_sync_date'
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
        'is_reviewed' => 'boolean',
        'manual_sync_date' => 'datetime'
    ];

    protected $attributes = [
        'ref_id' => null,
        'collected_date' => null,
        'received_date' => null,
        'reported_date' => null,
        'validated_by' => null,
        'is_completed' => false,
        'is_reviewed' => false,
        'manual_sync_date' => null,
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

    public function review(): HasOne
    {
        return $this->hasOne(DoctorReview::class, 'test_result_id', 'id');
    }

    public function aiReview(): HasOne
    {
        return $this->hasOne(AIReview::class, 'test_result_id', 'id');
    }

    /**
     * Get the user associated with the TestResult
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function record(): HasOne
    {
        return $this->hasOne(ReportRecord::class, 'test_result_id', 'id');
    }

    /**
     * Set the ref_id attribute.
     * Automatically converts ref_id to uppercase.
     *
     * @param  string|null  $value
     * @return void
     */
    public function setRefIdAttribute($value)
    {
        $this->attributes['ref_id'] = $value !== null ? strtoupper($value) : null;
    }
}