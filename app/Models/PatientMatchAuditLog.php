<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientMatchAuditLog extends Model
{
    use HasFactory;

    const ACTION_MATCH_ATTEMPTED = 'match_attempted';
    const ACTION_CANDIDATES_FOUND = 'candidates_found';
    const ACTION_NO_CANDIDATES_FOUND = 'no_candidates_found';
    const ACTION_CANDIDATE_APPROVED = 'candidate_approved';
    const ACTION_CANDIDATE_REJECTED = 'candidate_rejected';
    const ACTION_LINK_CREATED = 'link_created';
    const ACTION_LINK_REMOVED = 'link_removed';

    const TRIGGERED_BY_SYSTEM = 'system';
    const TRIGGERED_BY_ADMIN = 'admin';
    const TRIGGERED_BY_JOB = 'job';
    const TRIGGERED_BY_API = 'api';

    /**
     * Indicates if the model should be timestamped.
     *
     * This model only uses created_at, not updated_at.
     */
    public $timestamps = false;

    protected $fillable = [
        'patient_id',
        'customer_id',
        'match_candidate_id',
        'patient_customer_link_id',
        'action',
        'input_data',
        'output_data',
        'score_breakdown',
        'triggered_by',
        'user_id',
        'job_id',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'input_data' => 'array',
        'output_data' => 'array',
        'score_breakdown' => 'array',
        'created_at' => 'datetime',
    ];

    protected $attributes = [
        'triggered_by' => self::TRIGGERED_BY_SYSTEM,
    ];

    /**
     * Boot method to set created_at on create.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->created_at) {
                $model->created_at = now();
            }
        });
    }

    /**
     * Get the patient for this audit log.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the match candidate for this audit log.
     */
    public function matchCandidate(): BelongsTo
    {
        return $this->belongsTo(PatientMatchCandidate::class, 'match_candidate_id');
    }

    /**
     * Get the patient-customer link for this audit log.
     */
    public function patientCustomerLink(): BelongsTo
    {
        return $this->belongsTo(PatientCustomerLink::class, 'patient_customer_link_id');
    }

    /**
     * Get the user who triggered this action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for a specific action.
     */
    public function scopeForAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope for actions triggered by a specific source.
     */
    public function scopeTriggeredBy($query, string $triggeredBy)
    {
        return $query->where('triggered_by', $triggeredBy);
    }

    /**
     * Scope for a specific patient.
     */
    public function scopeForPatient($query, int $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    /**
     * Scope for a specific customer.
     */
    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }
}
