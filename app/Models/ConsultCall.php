<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConsultCall extends Model
{
    use HasFactory, SoftDeletes;

    // Enrollment Type
    const ENROLLMENT_TYPE_PRIMARY = 1;
    const ENROLLMENT_TYPE_FOLLOW_UP = 2;

    // Consent Call Status
    const CONSENT_STATUS_PENDING = 0;
    const CONSENT_STATUS_OBTAINED = 1;
    const CONSENT_STATUS_REFUSED = 2;

    // Scheduled Status
    const SCHEDULED_STATUS_PENDING = 0;
    const SCHEDULED_STATUS_CONFIRMED = 1;
    const SCHEDULED_STATUS_RESCHEDULED = 2;
    const SCHEDULED_STATUS_CANCELLED = 3;

    // Mode of Consultation
    const MODE_PENDING = 0;
    const MODE_PHONE = 1;
    const MODE_GOOGLE_MEET = 2;
    const MODE_WHATSAPP = 3;

    protected $fillable = [
        'patient_id',
        'customer_id',
        'is_eligible',
        'enrollment_date',
        'enrollment_type',
        'consent_call_status',
        'consent_call_date',
        'scheduled_status',
        'scheduled_call_date',
        'updated_scheduled_date',
        'handled_by',
        'mode_of_consultation',
        'closure_date',
        'final_remarks',
    ];

    protected $casts = [
        'patient_id' => 'integer',
        'customer_id' => 'integer',
        'is_eligible' => 'boolean',
        'enrollment_date' => 'datetime',
        'enrollment_type' => 'integer',
        'consent_call_status' => 'integer',
        'consent_call_date' => 'date',
        'scheduled_status' => 'integer',
        'scheduled_call_date' => 'date',
        'updated_scheduled_date' => 'date',
        'handled_by' => 'integer',
        'mode_of_consultation' => 'integer',
        'closure_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'is_eligible' => false,
        'enrollment_type' => self::ENROLLMENT_TYPE_PRIMARY,
        'consent_call_status' => self::CONSENT_STATUS_PENDING,
        'consent_call_date' => null,
        'scheduled_status' => self::SCHEDULED_STATUS_PENDING,
        'scheduled_call_date' => null,
        'mode_of_consultation' => self::MODE_PENDING,
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id', 'id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(ConsultCallDetails::class, 'consult_call_id', 'id');
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(ConsultCallFollowUp::class, 'consult_call_id', 'id');
    }
}
