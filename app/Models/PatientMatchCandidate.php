<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientMatchCandidate extends Model
{
    use HasFactory;

    const STATUS_PENDING_REVIEW = 'pending_review';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'patient_id',
        'source_ic',
        'source_ic_normalized',
        'source_refid',
        'source_refid_normalized',
        'source_name',
        'source_dob',
        'source_gender',
        'source_lab_code',
        'candidate_customer_id',
        'candidate_ic',
        'candidate_name',
        'candidate_dob',
        'candidate_gender',
        'candidate_refid',
        'ic_score',
        'ic_match_method',
        'refid_score',
        'refid_match_method',
        'name_score',
        'name_match_method',
        'dob_score',
        'gender_score',
        'confidence_score',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'match_algorithm_version',
    ];

    protected $casts = [
        'ic_score' => 'decimal:4',
        'refid_score' => 'decimal:4',
        'name_score' => 'decimal:4',
        'dob_score' => 'decimal:4',
        'gender_score' => 'decimal:4',
        'confidence_score' => 'decimal:4',
        'reviewed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING_REVIEW,
        'match_algorithm_version' => '1.1',
    ];

    /**
     * Get the patient that this candidate is for.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the admin user who reviewed this candidate.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Scope for pending review candidates.
     */
    public function scopePendingReview($query)
    {
        return $query->where('status', self::STATUS_PENDING_REVIEW);
    }

    /**
     * Scope for approved candidates.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope for rejected candidates.
     */
    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    /**
     * Scope to filter by minimum confidence score.
     */
    public function scopeMinConfidence($query, float $minScore)
    {
        return $query->where('confidence_score', '>=', $minScore);
    }

    /**
     * Scope to filter by lab code.
     */
    public function scopeForLabCode($query, string $labCode)
    {
        return $query->where('source_lab_code', $labCode);
    }

    /**
     * Check if candidate is pending review.
     */
    public function isPendingReview(): bool
    {
        return $this->status === self::STATUS_PENDING_REVIEW;
    }

    /**
     * Check if candidate has been approved.
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if candidate has been rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }
}
