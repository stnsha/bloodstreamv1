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
     * @param  array  $patientData  Patient data array with keys:
     *                              tc, ldlc, egfr, hba1c_percent, alt, age, bmi,
     *                              gender, hae, rcc, pcv, mcv, mch, mchc, rdw, s_iron, ferritin
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
     * @param  array  $patientData  Patient data array with keys:
     *                              tc, ldlc, egfr, hba1c_percent, alt, age, bmi,
     *                              gender, hae, rcc, pcv, mcv, mch, mchc, rdw, s_iron, ferritin
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

    /**
     * Condition 31: Hb 100-129 g/L
     */
    private function condition31(array $data): bool
    {
        if ($data['hae'] === null) {
            return false;
        }

        return $data['hae'] >= 100 && $data['hae'] <= 129;
    }

    /**
     * Condition 32: Ferritin <30 ug/L
     */
    private function condition32(array $data): bool
    {
        if ($data['ferritin'] === null) {
            return false;
        }

        return $data['ferritin'] < 30;
    }

    /**
     * Condition 33: Ferritin <15 ug/L
     */
    private function condition33(array $data): bool
    {
        if ($data['ferritin'] === null) {
            return false;
        }

        return $data['ferritin'] < 15;
    }

    /**
     * Condition 34: Serum Iron <10 umol/L
     */
    private function condition34(array $data): bool
    {
        if ($data['s_iron'] === null) {
            return false;
        }

        return $data['s_iron'] < 10;
    }

    /**
     * Condition 35: MCV <80 fL
     */
    private function condition35(array $data): bool
    {
        if ($data['mcv'] === null) {
            return false;
        }

        return $data['mcv'] < 80;
    }

    /**
     * Condition 36: Hb <100 g/L
     */
    private function condition36(array $data): bool
    {
        if ($data['hae'] === null) {
            return false;
        }

        return $data['hae'] < 100;
    }

    /**
     * Condition 37: MCV >100 fL
     */
    private function condition37(array $data): bool
    {
        if ($data['mcv'] === null) {
            return false;
        }

        return $data['mcv'] > 100;
    }

    /**
     * Condition 38: Hb 100-129 g/L AND Ferritin <30 ug/L
     */
    private function condition38(array $data): bool
    {
        if ($data['hae'] === null || $data['ferritin'] === null) {
            return false;
        }

        return $data['hae'] >= 100
            && $data['hae'] <= 129
            && $data['ferritin'] < 30;
    }

    /**
     * Condition 39: Hb 100-129 g/L AND Ferritin <15 ug/L
     */
    private function condition39(array $data): bool
    {
        if ($data['hae'] === null || $data['ferritin'] === null) {
            return false;
        }

        return $data['hae'] >= 100
            && $data['hae'] <= 129
            && $data['ferritin'] < 15;
    }

    /**
     * Condition 40: Hb <100 g/L AND Ferritin <30 ug/L
     */
    private function condition40(array $data): bool
    {
        if ($data['hae'] === null || $data['ferritin'] === null) {
            return false;
        }

        return $data['hae'] < 100
            && $data['ferritin'] < 30;
    }

    /**
     * Condition 41: Hb <100 g/L AND Ferritin <15 ug/L
     */
    private function condition41(array $data): bool
    {
        if ($data['hae'] === null || $data['ferritin'] === null) {
            return false;
        }

        return $data['hae'] < 100
            && $data['ferritin'] < 15;
    }

    /**
     * Condition 42: Hb 100-129 g/L AND Ferritin <30 ug/L AND MCV <80 fL AND MCH <27 pg
     */
    private function condition42(array $data): bool
    {
        if ($data['hae'] === null || $data['ferritin'] === null || $data['mcv'] === null || $data['mch'] === null) {
            return false;
        }

        return $data['hae'] >= 100
            && $data['hae'] <= 129
            && $data['ferritin'] < 30
            && $data['mcv'] < 80
            && $data['mch'] < 27;
    }

    /**
     * Condition 43: Ferritin <30 ug/L AND Serum Iron <10 umol/L
     */
    private function condition43(array $data): bool
    {
        if ($data['ferritin'] === null || $data['s_iron'] === null) {
            return false;
        }

        return $data['ferritin'] < 30
            && $data['s_iron'] < 10;
    }

    /**
     * Condition 44: Hb 100-129 g/L AND RDW >14.5% AND MCV <80 fL
     */
    private function condition44(array $data): bool
    {
        if ($data['hae'] === null || $data['rdw'] === null || $data['mcv'] === null) {
            return false;
        }

        return $data['hae'] >= 100
            && $data['hae'] <= 129
            && $data['rdw'] > 14.5
            && $data['mcv'] < 80;
    }

    /**
     * Condition 45: MCV <80 fL AND RCC >5.0 x10^12/L
     */
    private function condition45(array $data): bool
    {
        if ($data['mcv'] === null || $data['rcc'] === null) {
            return false;
        }

        return $data['mcv'] < 80
            && $data['rcc'] > 5.0;
    }

    /**
     * Condition 46: Hb 100-129 g/L AND Serum Iron <10 umol/L
     */
    private function condition46(array $data): bool
    {
        if ($data['hae'] === null || $data['s_iron'] === null) {
            return false;
        }

        return $data['hae'] >= 100
            && $data['hae'] <= 129
            && $data['s_iron'] < 10;
    }

    /**
     * Condition 47: Hb 100-129 g/L AND MCV <80 fL
     */
    private function condition47(array $data): bool
    {
        if ($data['hae'] === null || $data['mcv'] === null) {
            return false;
        }

        return $data['hae'] >= 100
            && $data['hae'] <= 129
            && $data['mcv'] < 80;
    }

    /**
     * Condition 48: Hb 100-129 g/L AND MCH <27 pg
     */
    private function condition48(array $data): bool
    {
        if ($data['hae'] === null || $data['mch'] === null) {
            return false;
        }

        return $data['hae'] >= 100
            && $data['hae'] <= 129
            && $data['mch'] < 27;
    }

    /**
     * Condition 49: Hb 100-129 g/L AND MCHC <320 g/L AND Ferritin <30 ug/L AND Serum Iron <10 umol/L
     */
    private function condition49(array $data): bool
    {
        if ($data['hae'] === null || $data['mchc'] === null || $data['ferritin'] === null || $data['s_iron'] === null) {
            return false;
        }

        return $data['hae'] >= 100
            && $data['hae'] <= 129
            && $data['mchc'] < 320
            && $data['ferritin'] < 30
            && $data['s_iron'] < 10;
    }

    /**
     * Condition 50: Hb 100-129 g/L AND RDW >14.5%
     */
    private function condition50(array $data): bool
    {
        if ($data['hae'] === null || $data['rdw'] === null) {
            return false;
        }

        return $data['hae'] >= 100
            && $data['hae'] <= 129
            && $data['rdw'] > 14.5;
    }

    /**
     * Condition 51: Hb 100-129 g/L AND PCV/HCT <0.36 L/L AND Female
     */
    private function condition51(array $data): bool
    {
        if ($data['hae'] === null || $data['pcv'] === null || $data['gender'] === null) {
            return false;
        }

        return $data['hae'] >= 100
            && $data['hae'] <= 129
            && $data['pcv'] < 0.36
            && $data['gender'] === 'F';
    }

    /**
     * Condition 52: Hb 100-129 g/L AND PCV/HCT <0.40 L/L AND Male
     */
    private function condition52(array $data): bool
    {
        if ($data['hae'] === null || $data['pcv'] === null || $data['gender'] === null) {
            return false;
        }

        return $data['hae'] >= 100
            && $data['hae'] <= 129
            && $data['pcv'] < 0.40
            && $data['gender'] === 'M';
    }

    /**
     * Condition 53: Hb 100-129 g/L AND RCC <3.9 x10^12/L AND Female
     */
    private function condition53(array $data): bool
    {
        if ($data['hae'] === null || $data['rcc'] === null || $data['gender'] === null) {
            return false;
        }

        return $data['hae'] >= 100
            && $data['hae'] <= 129
            && $data['rcc'] < 3.9
            && $data['gender'] === 'F';
    }

    /**
     * Condition 54: Hb 100-129 g/L AND RCC <4.3 x10^12/L AND Male
     */
    private function condition54(array $data): bool
    {
        if ($data['hae'] === null || $data['rcc'] === null || $data['gender'] === null) {
            return false;
        }

        return $data['hae'] >= 100
            && $data['hae'] <= 129
            && $data['rcc'] < 4.3
            && $data['gender'] === 'M';
    }

    /**
     * Condition 55: Serum Iron <10 umol/L AND MCH <27 pg
     */
    private function condition55(array $data): bool
    {
        if ($data['s_iron'] === null || $data['mch'] === null) {
            return false;
        }

        return $data['s_iron'] < 10
            && $data['mch'] < 27;
    }

    /**
     * Condition 56: Serum Iron <10 umol/L AND MCV <80 fL
     */
    private function condition56(array $data): bool
    {
        if ($data['s_iron'] === null || $data['mcv'] === null) {
            return false;
        }

        return $data['s_iron'] < 10
            && $data['mcv'] < 80;
    }

    /**
     * Condition 57: Serum Iron <10 umol/L AND MCHC <320 g/L
     */
    private function condition57(array $data): bool
    {
        if ($data['s_iron'] === null || $data['mchc'] === null) {
            return false;
        }

        return $data['s_iron'] < 10
            && $data['mchc'] < 320;
    }

    /**
     * Condition 58: Serum Iron <10 umol/L AND RDW >14.5%
     */
    private function condition58(array $data): bool
    {
        if ($data['s_iron'] === null || $data['rdw'] === null) {
            return false;
        }

        return $data['s_iron'] < 10
            && $data['rdw'] > 14.5;
    }

    /**
     * Condition 59: Serum Iron <10 umol/L AND PCV/HCT <0.36 L/L AND Female
     */
    private function condition59(array $data): bool
    {
        if ($data['s_iron'] === null || $data['pcv'] === null || $data['gender'] === null) {
            return false;
        }

        return $data['s_iron'] < 10
            && $data['pcv'] < 0.36
            && $data['gender'] === 'F';
    }

    /**
     * Condition 60: Serum Iron <10 umol/L AND PCV/HCT <0.40 L/L AND Male
     */
    private function condition60(array $data): bool
    {
        if ($data['s_iron'] === null || $data['pcv'] === null || $data['gender'] === null) {
            return false;
        }

        return $data['s_iron'] < 10
            && $data['pcv'] < 0.40
            && $data['gender'] === 'M';
    }

    /**
     * Condition 61: Serum Iron <10 umol/L AND RCC <3.9 x10^12/L AND Female
     */
    private function condition61(array $data): bool
    {
        if ($data['s_iron'] === null || $data['rcc'] === null || $data['gender'] === null) {
            return false;
        }

        return $data['s_iron'] < 10
            && $data['rcc'] < 3.9
            && $data['gender'] === 'F';
    }

    /**
     * Condition 62: Serum Iron <10 umol/L AND RCC <4.3 x10^12/L AND Male
     */
    private function condition62(array $data): bool
    {
        if ($data['s_iron'] === null || $data['rcc'] === null || $data['gender'] === null) {
            return false;
        }

        return $data['s_iron'] < 10
            && $data['rcc'] < 4.3
            && $data['gender'] === 'M';
    }

    /**
     * Condition 63: Serum Iron <10 umol/L AND Ferritin <30 ug/L
     */
    private function condition63(array $data): bool
    {
        if ($data['s_iron'] === null || $data['ferritin'] === null) {
            return false;
        }

        return $data['s_iron'] < 10
            && $data['ferritin'] < 30;
    }

    /**
     * Condition 64: Hb 100-129 g/L AND Serum Iron <10 umol/L AND MCH <27 pg
     */
    private function condition64(array $data): bool
    {
        if ($data['hae'] === null || $data['s_iron'] === null || $data['mch'] === null) {
            return false;
        }

        return $data['hae'] >= 100
            && $data['hae'] <= 129
            && $data['s_iron'] < 10
            && $data['mch'] < 27;
    }

    /**
     * Condition 65: Hb 100-129 g/L AND PCV/HCT <0.36 L/L AND Female AND Serum Iron <10 umol/L
     */
    private function condition65(array $data): bool
    {
        if ($data['hae'] === null || $data['pcv'] === null || $data['gender'] === null || $data['s_iron'] === null) {
            return false;
        }

        return $data['hae'] >= 100
            && $data['hae'] <= 129
            && $data['pcv'] < 0.36
            && $data['gender'] === 'F'
            && $data['s_iron'] < 10;
    }

    /**
     * Condition 66: Hb 100-129 g/L AND PCV/HCT <0.40 L/L AND Male AND Serum Iron <10 umol/L
     */
    private function condition66(array $data): bool
    {
        if ($data['hae'] === null || $data['pcv'] === null || $data['gender'] === null || $data['s_iron'] === null) {
            return false;
        }

        return $data['hae'] >= 100
            && $data['hae'] <= 129
            && $data['pcv'] < 0.40
            && $data['gender'] === 'M'
            && $data['s_iron'] < 10;
    }

    /**
     * Condition 67: Hb 100-129 AND Serum Iron <10 AND MCV <80 AND MCH <27 AND MCHC <320
     *               AND RDW >14.5% AND PCV <0.36 AND Female AND RCC <3.9 AND Ferritin <30
     */
    private function condition67(array $data): bool
    {
        if (
            $data['hae'] === null || $data['s_iron'] === null || $data['mcv'] === null ||
            $data['mch'] === null || $data['mchc'] === null || $data['rdw'] === null ||
            $data['pcv'] === null || $data['gender'] === null || $data['rcc'] === null ||
            $data['ferritin'] === null
        ) {
            return false;
        }

        return $data['hae'] >= 100
            && $data['hae'] <= 129
            && $data['s_iron'] < 10
            && $data['mcv'] < 80
            && $data['mch'] < 27
            && $data['mchc'] < 320
            && $data['rdw'] > 14.5
            && $data['pcv'] < 0.36
            && $data['gender'] === 'F'
            && $data['rcc'] < 3.9
            && $data['ferritin'] < 30;
    }

    /**
     * Condition 68: Hb 100-129 AND Serum Iron <10 AND MCV <80 AND MCH <27 AND MCHC <320
     *               AND RDW >14.5% AND PCV <0.40 AND Male AND RCC <4.3 AND Ferritin <30
     */
    private function condition68(array $data): bool
    {
        if (
            $data['hae'] === null || $data['s_iron'] === null || $data['mcv'] === null ||
            $data['mch'] === null || $data['mchc'] === null || $data['rdw'] === null ||
            $data['pcv'] === null || $data['gender'] === null || $data['rcc'] === null ||
            $data['ferritin'] === null
        ) {
            return false;
        }

        return $data['hae'] >= 100
            && $data['hae'] <= 129
            && $data['s_iron'] < 10
            && $data['mcv'] < 80
            && $data['mch'] < 27
            && $data['mchc'] < 320
            && $data['rdw'] > 14.5
            && $data['pcv'] < 0.40
            && $data['gender'] === 'M'
            && $data['rcc'] < 4.3
            && $data['ferritin'] < 30;
    }

    /**
     * Condition 69: Hb 100-129 AND Serum Iron <10 AND MCV <80 AND MCH <27 AND MCHC <320
     *               AND RDW >14.5% AND PCV <0.36 AND Female AND RCC <3.9 (no Ferritin)
     */
    private function condition69(array $data): bool
    {
        if (
            $data['hae'] === null || $data['s_iron'] === null || $data['mcv'] === null ||
            $data['mch'] === null || $data['mchc'] === null || $data['rdw'] === null ||
            $data['pcv'] === null || $data['gender'] === null || $data['rcc'] === null
        ) {
            return false;
        }

        return $data['hae'] >= 100
            && $data['hae'] <= 129
            && $data['s_iron'] < 10
            && $data['mcv'] < 80
            && $data['mch'] < 27
            && $data['mchc'] < 320
            && $data['rdw'] > 14.5
            && $data['pcv'] < 0.36
            && $data['gender'] === 'F'
            && $data['rcc'] < 3.9;
    }

    /**
     * Condition 70: Hb 100-129 AND Serum Iron <10 AND MCV <80 AND MCH <27 AND MCHC <320
     *               AND RDW >14.5% AND PCV <0.40 AND Male AND RCC <4.3 (no Ferritin)
     */
    private function condition70(array $data): bool
    {
        if (
            $data['hae'] === null || $data['s_iron'] === null || $data['mcv'] === null ||
            $data['mch'] === null || $data['mchc'] === null || $data['rdw'] === null ||
            $data['pcv'] === null || $data['gender'] === null || $data['rcc'] === null
        ) {
            return false;
        }

        return $data['hae'] >= 100
            && $data['hae'] <= 129
            && $data['s_iron'] < 10
            && $data['mcv'] < 80
            && $data['mch'] < 27
            && $data['mchc'] < 320
            && $data['rdw'] > 14.5
            && $data['pcv'] < 0.40
            && $data['gender'] === 'M'
            && $data['rcc'] < 4.3;
    }

    /**
     * Condition 71: Hb 100-129 AND MCV <80 AND MCH <27 AND MCHC <320
     *               AND RDW >14.5% AND PCV <0.36 AND Female AND RCC <3.9 (no Serum Iron)
     */
    private function condition71(array $data): bool
    {
        if (
            $data['hae'] === null || $data['mcv'] === null || $data['mch'] === null ||
            $data['mchc'] === null || $data['rdw'] === null || $data['pcv'] === null ||
            $data['gender'] === null || $data['rcc'] === null
        ) {
            return false;
        }

        return $data['hae'] >= 100
            && $data['hae'] <= 129
            && $data['mcv'] < 80
            && $data['mch'] < 27
            && $data['mchc'] < 320
            && $data['rdw'] > 14.5
            && $data['pcv'] < 0.36
            && $data['gender'] === 'F'
            && $data['rcc'] < 3.9;
    }

    /**
     * Condition 72: Hb 100-129 AND MCV <80 AND MCH <27 AND MCHC <320
     *               AND RDW >14.5% AND PCV <0.40 AND Male AND RCC <4.3 (no Serum Iron)
     */
    private function condition72(array $data): bool
    {
        if (
            $data['hae'] === null || $data['mcv'] === null || $data['mch'] === null ||
            $data['mchc'] === null || $data['rdw'] === null || $data['pcv'] === null ||
            $data['gender'] === null || $data['rcc'] === null
        ) {
            return false;
        }

        return $data['hae'] >= 100
            && $data['hae'] <= 129
            && $data['mcv'] < 80
            && $data['mch'] < 27
            && $data['mchc'] < 320
            && $data['rdw'] > 14.5
            && $data['pcv'] < 0.40
            && $data['gender'] === 'M'
            && $data['rcc'] < 4.3;
    }

    /**
     * Condition 73: Ferritin <15 ug/L AND MCV <80 fL AND MCH <27 pg
     */
    private function condition73(array $data): bool
    {
        if ($data['ferritin'] === null || $data['mcv'] === null || $data['mch'] === null) {
            return false;
        }

        return $data['ferritin'] < 15
            && $data['mcv'] < 80
            && $data['mch'] < 27;
    }

    /**
     * Condition 74: Ferritin <30 ug/L AND RDW >14.5%
     */
    private function condition74(array $data): bool
    {
        if ($data['ferritin'] === null || $data['rdw'] === null) {
            return false;
        }

        return $data['ferritin'] < 30
            && $data['rdw'] > 14.5;
    }

    /**
     * Condition 75: Hb 100-129 g/L AND MCV <80 fL AND RCC >5.0 x10^12/L
     */
    private function condition75(array $data): bool
    {
        if ($data['hae'] === null || $data['mcv'] === null || $data['rcc'] === null) {
            return false;
        }

        return $data['hae'] >= 100
            && $data['hae'] <= 129
            && $data['mcv'] < 80
            && $data['rcc'] > 5.0;
    }
}
