<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConsultCallFollowUp extends Model
{
    use HasFactory, SoftDeletes;

    // Follow-up Type
    const FOLLOWUP_TYPE_NONE = 0;
    const FOLLOWUP_TYPE_BLOOD_TEST_AND_REVIEW = 1;
    const FOLLOWUP_TYPE_REVIEW_ONLY = 2;

    // Next Follow-up
    const NEXT_FOLLOWUP_NONE = 0;
    const NEXT_FOLLOWUP_1_MONTH = 1;
    const NEXT_FOLLOWUP_3_MONTHS = 2;
    const NEXT_FOLLOWUP_6_MONTHS = 3;

    // Follow-up Reminder
    const FOLLOWUP_REMINDER_PENDING = 0;
    const FOLLOWUP_REMINDER_COMPLETED = 1;
    const FOLLOWUP_REMINDER_RESCHEDULED = 2;
    const FOLLOWUP_REMINDER_CANCELLED = 3;

    protected $fillable = [
        'consult_call_id',
        'followup_type',
        'next_followup',
        'followup_date',
        'is_blood_test_required',
        'mode_of_conversion',
        'referral_to',
        'my_referral_id',
        'followup_reminder',
        'rescheduled_date',
        'remarks',
    ];

    protected $casts = [
        'consult_call_id' => 'integer',
        'followup_type' => 'integer',
        'next_followup' => 'integer',
        'followup_date' => 'datetime',
        'is_blood_test_required' => 'boolean',
        'mode_of_conversion' => 'integer',
        'referral_to' => 'integer',
        'my_referral_id' => 'integer',
        'followup_reminder' => 'integer',
        'rescheduled_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'followup_type' => self::FOLLOWUP_TYPE_NONE,
        'next_followup' => self::NEXT_FOLLOWUP_NONE,
        'followup_date' => null,
        'is_blood_test_required' => false,
        'my_referral_id' => null,
        'followup_reminder' => self::FOLLOWUP_REMINDER_PENDING,
        'rescheduled_date' => null,
        'remarks' => null,
    ];

    public function consultCall(): BelongsTo
    {
        return $this->belongsTo(ConsultCall::class, 'consult_call_id', 'id');
    }
}
