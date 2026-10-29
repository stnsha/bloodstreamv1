<?php

namespace App\Models\ARR;

use App\Models\Patient;
use App\Models\TestResult;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Cluster (ARR) enrollment. Holds only the enrollment record; documentation and
 * follow-up for ARR are managed outside this system.
 */
class ARREnrollment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'arr_enrollments';

    protected $fillable = [
        'test_result_id',
        'patient_id',
        'customer_id',
        'outlet_id',
        'enrollment_date',
        'handled_by',
    ];

    protected $casts = [
        'test_result_id' => 'integer',
        'patient_id' => 'integer',
        'customer_id' => 'integer',
        'outlet_id' => 'integer',
        'enrollment_date' => 'datetime',
        'handled_by' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * True when the test result is enrolled in ARR. An ARR-enrolled result gets
     * no AI review (or MyHealth feed); ARR is handled outside this system.
     */
    public static function existsForTestResult(int $testResultId): bool
    {
        return self::where('test_result_id', $testResultId)->exists();
    }

    public function testResult(): BelongsTo
    {
        return $this->belongsTo(TestResult::class, 'test_result_id', 'id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id', 'id');
    }
}
