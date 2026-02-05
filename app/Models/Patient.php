<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use HasFactory, SoftDeletes;

    const IC_TYPE_NRIC = 'NRIC';
    const IC_TYPE_OTHERS = 'OTHERS';

    const GENDER_FEMALE = 'F';
    const GENDER_MALE = 'M';

    protected $fillable = ['icno', 'ic_type', 'name', 'dob', 'age', 'gender', 'tel'];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'ic_type' => 'NRIC',
        'name' => null,
        'dob' => null,
        'age' => null,
        'gender' => null,
        'tel' => null,
    ];

    public function testResults(): HasMany
    {
        return $this->hasMany(TestResult::class, 'patient_id', 'id');
    }

    /**
     * Get the customer link for this patient.
     */
    public function customerLink(): HasOne
    {
        return $this->hasOne(PatientCustomerLink::class);
    }

    /**
     * Get the match candidates for this patient.
     */
    public function matchCandidates(): HasMany
    {
        return $this->hasMany(PatientMatchCandidate::class);
    }

    /**
     * Check if patient has a confirmed customer link.
     */
    public function hasCustomerLink(): bool
    {
        return $this->customerLink()->exists();
    }

    /**
     * Check if patient has pending match candidates.
     */
    public function hasPendingMatchCandidates(): bool
    {
        return $this->matchCandidates()
            ->where('status', PatientMatchCandidate::STATUS_PENDING_REVIEW)
            ->exists();
    }
}