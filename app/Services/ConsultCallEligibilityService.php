<?php

namespace App\Services;

use App\Constants\ConsultCall\ClinicalCondition;
use App\Constants\ConsultCall\PanelPanelItem;
use App\Models\ConsultCall;
use App\Models\ConsultCallDetails;
use App\Models\Patient;
use App\Models\TestResult;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConsultCallEligibilityService
{
    protected ConditionEvaluatorService $conditionEvaluator;

    protected MyHealthService $myHealthService;

    public function __construct(ConditionEvaluatorService $conditionEvaluator, MyHealthService $myHealthService)
    {
        $this->conditionEvaluator = $conditionEvaluator;
        $this->myHealthService = $myHealthService;
    }

    /**
     * Check consult call eligibility for a test result and create records if a condition matches.
     *
     * @param  TestResult  $testResult  The completed test result
     * @param  int  $patientId  The patient ID
     * @param  int  $customerId  The ODB customer ID
     */
    public function checkAndCreate(TestResult $testResult, int $patientId, int $customerId): void
    {
        Log::info('ConsultCallEligibilityService: Starting eligibility check', [
            'test_result_id' => $testResult->id,
            'patient_id' => $patientId,
            'customer_id' => $customerId,
        ]);

        // Duplicate guard: skip if this test_result_id already has a ConsultCallDetails record
        if (ConsultCallDetails::where('test_result_id', $testResult->id)->exists()) {
            Log::info('ConsultCallEligibilityService: Skipping, ConsultCallDetails already exists for test result', [
                'test_result_id' => $testResult->id,
            ]);

            return;
        }

        // Load test result items for consult call panel IDs
        $items = $testResult->testResultItems()
            ->whereIn('panel_panel_item_id', PanelPanelItem::ALL_IDS)
            ->get();

        // Check that at least one item exists per each required category
        if (! $this->hasAllRequiredCategories($items)) {
            Log::info('ConsultCallEligibilityService: Skipping, incomplete panel categories', [
                'test_result_id' => $testResult->id,
                'items_count' => $items->count(),
            ]);

            return;
        }

        // Build patient data array
        $patient = Patient::find($patientId);
        if (! $patient) {
            Log::warning('ConsultCallEligibilityService: Patient not found', [
                'patient_id' => $patientId,
            ]);

            return;
        }

        $referenceDate = $testResult->collected_date ?? $testResult->reported_date;
        $age = $this->resolvePatientAge($patient, $referenceDate ? Carbon::parse($referenceDate) : null);

        // Resolve BMI from MyHealth
        $bmi = null;
        if ($patient->icno) {
            try {
                $bmi = $this->myHealthService->getPatientBMI(
                    $patient->icno,
                    $referenceDate
                );
            } catch (\Throwable $e) {
                Log::warning('ConsultCallEligibilityService: Failed to retrieve BMI', [
                    'patient_id' => $patientId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $patientData = [
            'tc' => $this->getPanelValue($items, 'tc'),
            'ldlc' => $this->getPanelValue($items, 'ldlc'),
            'egfr' => $this->getPanelValue($items, 'egfr'),
            'hba1c_percent' => $this->getPanelValue($items, 'hba1c_percent'),
            'alt' => $this->getPanelValue($items, 'alt'),
            'age' => $age,
            'bmi' => $bmi,
        ];

        // Evaluate against all conditions
        $conditionId = $this->conditionEvaluator->evaluateSinglePatient($patientData);

        if ($conditionId === null) {
            Log::info('ConsultCallEligibilityService: Patient is healthy, no condition matched', [
                'test_result_id' => $testResult->id,
                'patient_id' => $patientId,
            ]);

            return;
        }

        // Condition matched - create records
        try {
            DB::beginTransaction();

            $consultCall = ConsultCall::firstOrCreate(
                [
                    'patient_id' => $patientId,
                    'customer_id' => $customerId,
                ],
                [
                    'is_eligible' => true,
                    'enrollment_date' => now(),
                    'enrollment_type' => ConsultCall::ENROLLMENT_TYPE_PRIMARY,
                    'consent_call_status' => ConsultCall::CONSENT_STATUS_PENDING,
                    'scheduled_status' => ConsultCall::SCHEDULED_STATUS_PENDING,
                    'mode_of_consultation' => ConsultCall::MODE_PENDING,
                ]
            );

            $wasExisting = ! $consultCall->wasRecentlyCreated;

            ConsultCallDetails::create([
                'consult_call_id' => $consultCall->id,
                'clinical_condition_id' => $conditionId,
                'test_result_id' => $testResult->id,
            ]);

            DB::commit();

            $condition = ClinicalCondition::getCondition($conditionId);

            Log::info('ConsultCallEligibilityService: Consult call record created', [
                'test_result_id' => $testResult->id,
                'patient_id' => $patientId,
                'customer_id' => $customerId,
                'consult_call_id' => $consultCall->id,
                'clinical_condition_id' => $conditionId,
                'condition_description' => $condition['description'] ?? null,
                'was_existing_consult_call' => $wasExisting,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('ConsultCallEligibilityService: Failed to create consult call records', [
                'test_result_id' => $testResult->id,
                'patient_id' => $patientId,
                'customer_id' => $customerId,
                'clinical_condition_id' => $conditionId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            throw $e;
        }
    }

    /**
     * Check that at least one test result item exists per each required category.
     */
    private function hasAllRequiredCategories(Collection $items): bool
    {
        $panelItemIds = $items->pluck('panel_panel_item_id')->toArray();

        foreach (PanelPanelItem::REQUIRED_CATEGORIES as $category => $ids) {
            $found = false;
            foreach ($ids as $id) {
                if (in_array($id, $panelItemIds)) {
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the numeric value for a panel category from the test result items.
     */
    private function getPanelValue(Collection $items, string $category): ?float
    {
        $ids = PanelPanelItem::REQUIRED_CATEGORIES[$category] ?? [];

        foreach ($ids as $id) {
            $item = $items->firstWhere('panel_panel_item_id', $id);
            if ($item && $item->value !== null && $item->value !== '') {
                return (float) $item->value;
            }
        }

        return null;
    }

    /**
     * Resolve patient age from the patient model or calculate from DOB.
     */
    private function resolvePatientAge(Patient $patient, ?Carbon $collectedDate): ?int
    {
        if ($patient->age !== null) {
            return (int) $patient->age;
        }

        if ($patient->dob && $collectedDate) {
            return calculatePatientAge($patient->dob, $collectedDate->toDateString());
        }

        if ($patient->dob) {
            return Carbon::parse($patient->dob)->age;
        }

        return null;
    }
}
