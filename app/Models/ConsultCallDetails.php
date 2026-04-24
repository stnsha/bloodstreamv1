<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConsultCallDetails extends Model
{
    use HasFactory, SoftDeletes;

    // Action
    const ACTION_REFER_INTERNAL = 1;
    const ACTION_REFER_EXTERNAL = 2;
    const ACTION_END_PROCESS = 3;

    // Consult Status
    const CONSULT_STATUS_PENDING = 0;
    const CONSULT_STATUS_COMPLETED = 1;
    const CONSULT_STATUS_NO_SHOW = 2;
    const CONSULT_STATUS_CANCELLED = 3;

    // Process Status
    const PROCESS_STATUS_ACTIVE = 1;
    const PROCESS_STATUS_ESCALATED = 2;
    const PROCESS_STATUS_CLOSED = 3;

    protected $fillable = [
        'consult_call_id',
        'clinical_condition_id',
        'test_result_id',
        'documentation',
        'diagnosis',
        'treatment_plan',
        'rx_issued',
        'action',
        'consult_status',
        'process_status',
        'consulted_by',
        'consult_date',
        'remarks',
        'is_draft',
    ];

    protected $casts = [
        'consult_call_id' => 'integer',
        'clinical_condition_id' => 'int',
        'test_result_id' => 'integer',
        'rx_issued' => 'boolean',
        'action' => 'integer',
        'consult_status' => 'integer',
        'process_status' => 'integer',
        'consulted_by' => 'integer',
        'consult_date' => 'datetime',
        'is_draft' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'rx_issued' => false,
        'consult_status' => self::CONSULT_STATUS_PENDING,
        'process_status' => self::PROCESS_STATUS_ACTIVE,
        'remarks' => null,
        'is_draft' => 0,
    ];

    public function consultCall(): BelongsTo
    {
        return $this->belongsTo(ConsultCall::class, 'consult_call_id', 'id');
    }

    public function clinicalCondition(): BelongsTo
    {
        return $this->belongsTo(ClinicalCondition::class, 'clinical_condition_id', 'id');
    }

    public function testResult(): BelongsTo
    {
        return $this->belongsTo(TestResult::class, 'test_result_id', 'id');
    }
}
