<?php

namespace App\Services\ARR;

use App\Models\ARR\ARREnrollment;
use App\Models\TestResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ARREnrollmentService
{
    /**
     * Enroll a cluster (ARR) patient once a test result has passed the outlet
     * cluster check and matched a cluster clinical condition. One enrollment per
     * test result: a re-delivery of the same result keeps the existing row and
     * updates its clinical_condition_id when the re-evaluated condition changed.
     *
     * @throws Throwable when the enrollment cannot be stored
     */
    public function enroll(TestResult $testResult, int $patientId, int $customerId, ?int $outletId, int $conditionId): ARREnrollment
    {
        Log::info('ARREnrollmentService: starting enrollment', [
            'test_result_id' => $testResult->id,
            'patient_id' => $patientId,
            'customer_id' => $customerId,
            'outlet_id' => $outletId,
            'clinical_condition_id' => $conditionId,
        ]);

        try {
            DB::beginTransaction();

            $enrollment = ARREnrollment::firstOrCreate(
                ['test_result_id' => $testResult->id],
                [
                    'patient_id' => $patientId,
                    'customer_id' => $customerId,
                    'outlet_id' => $outletId,
                    'clinical_condition_id' => $conditionId,
                    'enrollment_date' => now(),
                ]
            );

            $conditionChanged = ! $enrollment->wasRecentlyCreated
                && $enrollment->clinical_condition_id !== $conditionId;

            if ($conditionChanged) {
                $previousConditionId = $enrollment->clinical_condition_id;
                $enrollment->update(['clinical_condition_id' => $conditionId]);

                Log::info('ARREnrollmentService: clinical condition updated on re-evaluation', [
                    'test_result_id' => $testResult->id,
                    'arr_enrollment_id' => $enrollment->id,
                    'previous_clinical_condition_id' => $previousConditionId,
                    'clinical_condition_id' => $conditionId,
                ]);
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('ARREnrollmentService: enrollment failed', [
                'test_result_id' => $testResult->id,
                'patient_id' => $patientId,
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        Log::info('ARREnrollmentService: enrollment completed', [
            'test_result_id' => $testResult->id,
            'arr_enrollment_id' => $enrollment->id,
            'already_enrolled' => ! $enrollment->wasRecentlyCreated,
            'clinical_condition_id' => $conditionId,
        ]);

        return $enrollment;
    }
}
