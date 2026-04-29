<?php

namespace App\Services;

use App\Models\ClinicalCondition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ConditionEvaluatorService
{
    /**
     * Evaluate all conditions against a collection of patient data.
     *
     * Each patient is assigned to exactly ONE condition (the most specific one they match).
     * Patients matching no conditions are counted as "Healthy".
     * This ensures percentages sum to 100%.
     *
     * @param  Collection  $evaluatableData  Collection of patient data arrays
     * @param  int  $totalAllResults  Total count of all results before filtering
     * @param  int  $totalFilteredResults  Total count of filtered results
     * @return array Array of condition statistics
     */
    public function evaluateAll(Collection $evaluatableData, int $totalAllResults, int $totalFilteredResults): array
    {
        Log::info('Starting condition evaluation (exclusive assignment)', [
            'evaluatable_count' => $evaluatableData->count(),
            'total_all_results' => $totalAllResults,
            'total_filtered_results' => $totalFilteredResults,
        ]);

        $conditions = ClinicalCondition::getAll();

        // Get conditions sorted by criteria_count DESC (most specific first)
        $sortedConditionIds = ClinicalCondition::getIdsSortedByPriority();

        // Initialize counts keyed by condition ID; 0 = healthy
        $conditionCounts = [0 => 0];
        foreach ($sortedConditionIds as $id) {
            $conditionCounts[$id] = 0;
        }

        // Assign each patient to exactly ONE condition (first match wins)
        foreach ($evaluatableData as $patientData) {
            $assignedCondition = 0; // Default to healthy (no condition matched)

            // Check conditions from highest criteria count to lowest
            // First match wins - patient is assigned to this condition only
            foreach ($sortedConditionIds as $conditionId) {
                if ($this->evaluateCondition($conditionId, $patientData)) {
                    $assignedCondition = $conditionId;
                    break;
                }
            }

            $conditionCounts[$assignedCondition]++;
        }

        // Build statistics array in original order (excludes healthy row)
        $statistics = [];
        $conditions = array_filter(
            $conditions,
            fn ($c) => ($c['criteria_count'] ?? 0) > 0
        );
        foreach ($conditions as $conditionId => $conditionConfig) {
            $totalMet = $conditionCounts[$conditionId];

            $statistics[] = [
                'condition_id' => $conditionId,
                'condition_description' => $conditionConfig['description'],
                'total_met' => $totalMet,
                'percentage_of_filtered' => $totalFilteredResults > 0
                    ? round(($totalMet / $totalFilteredResults) * 100, 2)
                    : 0.00,
                'percentage_of_total' => $totalAllResults > 0
                    ? round(($totalMet / $totalAllResults) * 100, 2)
                    : 0.00,
            ];
        }

        // Add "Healthy" category at the end (condition_id = 0)
        $healthyCount = $conditionCounts[0];
        $statistics[] = [
            'condition_id' => 0,
            'condition_description' => 'Healthy (no conditions met)',
            'total_met' => $healthyCount,
            'percentage_of_filtered' => $totalFilteredResults > 0
                ? round(($healthyCount / $totalFilteredResults) * 100, 2)
                : 0.00,
            'percentage_of_total' => $totalAllResults > 0
                ? round(($healthyCount / $totalAllResults) * 100, 2)
                : 0.00,
        ];

        Log::info('Condition evaluation completed (exclusive assignment)', [
            'conditions_evaluated' => count($statistics),
            'healthy_count' => $healthyCount,
        ]);

        return $statistics;
    }

    /**
     * Evaluate a single patient against all conditions, returning the first match.
     *
     * Iterates conditions from highest criteria_count (most specific) to lowest.
     * Returns the first matching condition ID, or null if healthy.
     *
     * @param  array  $patientData  Patient data array with keys: tc, ldlc, egfr, hba1c_percent, alt, age, bmi
     * @return int|null The matching condition ID, or null if no condition matched
     */
    public function evaluateSinglePatient(array $patientData): ?int
    {
        $sortedConditionIds = ClinicalCondition::getIdsSortedByPriority();

        foreach ($sortedConditionIds as $conditionId) {
            if ($this->evaluateCondition($conditionId, $patientData)) {
                return $conditionId;
            }
        }

        return null;
    }

    /**
     * Evaluate a single condition against patient data.
     *
     * @param  int  $conditionId  The condition ID to evaluate
     * @param  array  $patientData  Patient data array with keys: tc, ldlc, egfr, hba1c_percent, alt, age, bmi
     * @return bool True if the condition is met, false otherwise
     */
    public function evaluateCondition(int $conditionId, array $patientData): bool
    {
        $condition = ClinicalCondition::getCondition($conditionId);
        if (! $condition) {
            return false;
        }

        $method = $condition['evaluator'];
        if (! method_exists($this, $method)) {
            Log::warning('Condition evaluator method not found', [
                'condition_id' => $conditionId,
                'method' => $method,
            ]);

            return false;
        }

        return $this->$method($patientData);
    }

    /**
     * Condition 1: LDL > 2.6 AND HbA1c 5.7-6.2
     */
    private function condition1(array $data): bool
    {
        if ($data['ldlc'] === null || $data['hba1c_percent'] === null) {
            return false;
        }

        return $data['ldlc'] > 2.6
            && $data['hba1c_percent'] >= 5.7
            && $data['hba1c_percent'] <= 6.2;
    }

    /**
     * Condition 2: LDL > 2.6 AND HbA1c >= 6.3
     */
    private function condition2(array $data): bool
    {
        if ($data['ldlc'] === null || $data['hba1c_percent'] === null) {
            return false;
        }

        return $data['ldlc'] > 2.6
            && $data['hba1c_percent'] >= 6.3;
    }

    /**
     * Condition 3: TC > 5.2 AND HbA1c 5.7-6.2
     */
    private function condition3(array $data): bool
    {
        if ($data['tc'] === null || $data['hba1c_percent'] === null) {
            return false;
        }

        return $data['tc'] > 5.2
            && $data['hba1c_percent'] >= 5.7
            && $data['hba1c_percent'] <= 6.2;
    }

    /**
     * Condition 4: TC > 5.2 AND HbA1c >= 6.3
     */
    private function condition4(array $data): bool
    {
        if ($data['tc'] === null || $data['hba1c_percent'] === null) {
            return false;
        }

        return $data['tc'] > 5.2
            && $data['hba1c_percent'] >= 6.3;
    }

    /**
     * Condition 5: TC > 5.2 AND LDL > 2.6 AND HbA1c 5.7-6.2
     */
    private function condition5(array $data): bool
    {
        if ($data['tc'] === null || $data['ldlc'] === null || $data['hba1c_percent'] === null) {
            return false;
        }

        return $data['tc'] > 5.2
            && $data['ldlc'] > 2.6
            && $data['hba1c_percent'] >= 5.7
            && $data['hba1c_percent'] <= 6.2;
    }

    /**
     * Condition 6: TC > 5.2 AND LDL > 2.6 AND HbA1c >= 6.3
     */
    private function condition6(array $data): bool
    {
        if ($data['tc'] === null || $data['ldlc'] === null || $data['hba1c_percent'] === null) {
            return false;
        }

        return $data['tc'] > 5.2
            && $data['ldlc'] > 2.6
            && $data['hba1c_percent'] >= 6.3;
    }

    /**
     * Condition 7: CKD eGFR < 30 AND HbA1c >= 6.3 AND LDL > 1.4
     */
    private function condition7(array $data): bool
    {
        if ($data['egfr'] === null || $data['hba1c_percent'] === null || $data['ldlc'] === null) {
            return false;
        }

        return $data['egfr'] < 30
            && $data['hba1c_percent'] >= 6.3
            && $data['ldlc'] > 1.4;
    }

    /**
     * Condition 8: CKD eGFR 30-60 AND HbA1c >= 6.3 AND LDL > 1.8
     */
    private function condition8(array $data): bool
    {
        if ($data['egfr'] === null || $data['hba1c_percent'] === null || $data['ldlc'] === null) {
            return false;
        }

        return $data['egfr'] >= 30
            && $data['egfr'] <= 60
            && $data['hba1c_percent'] >= 6.3
            && $data['ldlc'] > 1.8;
    }

    /**
     * Condition 9: eGFR > 60 AND HbA1c >= 6.3 AND LDL > 2.6
     */
    private function condition9(array $data): bool
    {
        if ($data['egfr'] === null || $data['hba1c_percent'] === null || $data['ldlc'] === null) {
            return false;
        }

        return $data['egfr'] > 60
            && $data['hba1c_percent'] >= 6.3
            && $data['ldlc'] > 2.6;
    }

    /**
     * Condition 10: ALT > 120 AND TC > 5.2 AND LDL > 2.6
     */
    private function condition10(array $data): bool
    {
        if ($data['alt'] === null || $data['tc'] === null || $data['ldlc'] === null) {
            return false;
        }

        return $data['alt'] > 120
            && $data['tc'] > 5.2
            && $data['ldlc'] > 2.6;
    }

    /**
     * Condition 11: eGFR 30-44 AND HbA1c >= 6.3
     */
    private function condition11(array $data): bool
    {
        if ($data['egfr'] === null || $data['hba1c_percent'] === null) {
            return false;
        }

        return $data['egfr'] >= 30
            && $data['egfr'] <= 44
            && $data['hba1c_percent'] >= 6.3;
    }

    /**
     * Condition 12: eGFR < 30 AND HbA1c >= 6.3
     */
    private function condition12(array $data): bool
    {
        if ($data['egfr'] === null || $data['hba1c_percent'] === null) {
            return false;
        }

        return $data['egfr'] < 30
            && $data['hba1c_percent'] >= 6.3;
    }

    /**
     * Condition 13: Age < 50 AND HbA1c 6.3-6.5
     */
    private function condition13(array $data): bool
    {
        if ($data['age'] === null || $data['hba1c_percent'] === null) {
            return false;
        }

        return $data['age'] < 50
            && $data['hba1c_percent'] >= 6.3
            && $data['hba1c_percent'] <= 6.5;
    }

    /**
     * Condition 14: Age > 50 AND HbA1c > 6.3 AND eGFR < 45
     */
    private function condition14(array $data): bool
    {
        if ($data['age'] === null || $data['hba1c_percent'] === null || $data['egfr'] === null) {
            return false;
        }

        return $data['age'] > 50
            && $data['hba1c_percent'] > 6.3
            && $data['egfr'] < 45;
    }

    /**
     * Condition 15: Age > 30 AND BMI > 23 AND HbA1c 5.7-6.2
     */
    private function condition15(array $data): bool
    {
        if ($data['age'] === null || $data['bmi'] === null || $data['hba1c_percent'] === null) {
            return false;
        }

        return $data['age'] > 30
            && $data['bmi'] > 23
            && $data['hba1c_percent'] >= 5.7
            && $data['hba1c_percent'] <= 6.2;
    }

    /**
     * Condition 16: Age > 30 AND BMI > 23 AND HbA1c >= 6.3
     */
    private function condition16(array $data): bool
    {
        if ($data['age'] === null || $data['bmi'] === null || $data['hba1c_percent'] === null) {
            return false;
        }

        return $data['age'] > 30
            && $data['bmi'] > 23
            && $data['hba1c_percent'] >= 6.3;
    }

    /**
     * Condition 17: Age > 30 AND BMI > 23 AND TC > 5.2 AND LDL > 2.6
     */
    private function condition17(array $data): bool
    {
        if ($data['age'] === null || $data['bmi'] === null || $data['tc'] === null || $data['ldlc'] === null) {
            return false;
        }

        return $data['age'] > 30
            && $data['bmi'] > 23
            && $data['tc'] > 5.2
            && $data['ldlc'] > 2.6;
    }

    /**
     * Condition 18: Age > 30 AND BMI > 23 AND HbA1c >= 6.3 AND TC > 5.2 AND LDL > 2.6
     */
    private function condition18(array $data): bool
    {
        if ($data['age'] === null || $data['bmi'] === null || $data['hba1c_percent'] === null || $data['tc'] === null || $data['ldlc'] === null) {
            return false;
        }

        return $data['age'] > 30
            && $data['bmi'] > 23
            && $data['hba1c_percent'] >= 6.3
            && $data['tc'] > 5.2
            && $data['ldlc'] > 2.6;
    }

    /**
     * Condition 19: HbA1c 5.7-6.2 AND (LDL 2.6-3.0 OR TC 5.2-6.0)
     */
    private function condition19(array $data): bool
    {
        if ($data['hba1c_percent'] === null) {
            return false;
        }

        $hba1cInRange = $data['hba1c_percent'] >= 5.7 && $data['hba1c_percent'] <= 6.2;
        if (! $hba1cInRange) {
            return false;
        }

        $ldlInRange = $data['ldlc'] !== null && $data['ldlc'] >= 2.6 && $data['ldlc'] <= 3.0;
        $tcInRange = $data['tc'] !== null && $data['tc'] >= 5.2 && $data['tc'] <= 6.0;

        return $ldlInRange || $tcInRange;
    }

    /**
     * Condition 20: HbA1c 5.7-6.2 AND BMI > 23
     */
    private function condition20(array $data): bool
    {
        if ($data['hba1c_percent'] === null || $data['bmi'] === null) {
            return false;
        }

        return $data['hba1c_percent'] >= 5.7
            && $data['hba1c_percent'] <= 6.2
            && $data['bmi'] > 23;
    }

    /**
     * Condition 21: HbA1c 5.7-6.2 AND ALT 40-120
     */
    private function condition21(array $data): bool
    {
        if ($data['hba1c_percent'] === null || $data['alt'] === null) {
            return false;
        }

        return $data['hba1c_percent'] >= 5.7
            && $data['hba1c_percent'] <= 6.2
            && $data['alt'] >= 40
            && $data['alt'] <= 120;
    }

    /**
     * Condition 22: HbA1c 5.7-6.2 AND eGFR 45-60
     */
    private function condition22(array $data): bool
    {
        if ($data['hba1c_percent'] === null || $data['egfr'] === null) {
            return false;
        }

        return $data['hba1c_percent'] >= 5.7
            && $data['hba1c_percent'] <= 6.2
            && $data['egfr'] >= 45
            && $data['egfr'] <= 60;
    }

    /**
     * Condition 23: HbA1c >= 6.3 AND (LDL > 2.6 OR TC > 5.2)
     */
    private function condition23(array $data): bool
    {
        if ($data['hba1c_percent'] === null) {
            return false;
        }

        if ($data['hba1c_percent'] < 6.3) {
            return false;
        }

        $ldlAboveThreshold = $data['ldlc'] !== null && $data['ldlc'] > 2.6;
        $tcAboveThreshold = $data['tc'] !== null && $data['tc'] > 5.2;

        return $ldlAboveThreshold || $tcAboveThreshold;
    }

    /**
     * Condition 24: HbA1c >= 6.3 AND eGFR 30-60
     */
    private function condition24(array $data): bool
    {
        if ($data['hba1c_percent'] === null || $data['egfr'] === null) {
            return false;
        }

        return $data['hba1c_percent'] >= 6.3
            && $data['egfr'] >= 30
            && $data['egfr'] <= 60;
    }

    /**
     * Condition 25: ALT 40-120 AND HbA1c 5.7-6.2 AND LDL 2.6-3.0 AND TC 5.2-6.0
     */
    private function condition25(array $data): bool
    {
        if ($data['alt'] === null || $data['hba1c_percent'] === null || $data['ldlc'] === null || $data['tc'] === null) {
            return false;
        }

        return $data['alt'] >= 40
            && $data['alt'] <= 120
            && $data['hba1c_percent'] >= 5.7
            && $data['hba1c_percent'] <= 6.2
            && $data['ldlc'] >= 2.6
            && $data['ldlc'] <= 3.0
            && $data['tc'] >= 5.2
            && $data['tc'] <= 6.0;
    }

    /**
     * Condition 27: TC > 5.2
     */
    private function condition27(array $data): bool
    {
        if ($data['tc'] === null) {
            return false;
        }

        return $data['tc'] > 5.2;
    }

    /**
     * Condition 28: LDL > 2.6
     */
    private function condition28(array $data): bool
    {
        if ($data['ldlc'] === null) {
            return false;
        }

        return $data['ldlc'] > 2.6;
    }

    /**
     * Condition 29: LDL > 2.6 AND TC > 5.2
     */
    private function condition29(array $data): bool
    {
        if ($data['ldlc'] === null || $data['tc'] === null) {
            return false;
        }

        return $data['ldlc'] > 2.6
            && $data['tc'] > 5.2;
    }

    /**
     * Condition 30: HbA1c >= 6.3
     */
    private function condition30(array $data): bool
    {
        if ($data['hba1c_percent'] === null) {
            return false;
        }

        return $data['hba1c_percent'] >= 6.3;
    }
}
