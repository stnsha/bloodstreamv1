<?php

namespace App\Constants\ConsultCall;

class ClinicalCondition
{
    /**
     * All clinical conditions for evaluation.
     * Each condition has: id, description, evaluator method name.
     *
     * Threshold boundaries are EXCLUSIVE (e.g., LDL > 2.6 means 2.6 does NOT match).
     */
    public const CONDITIONS = [
        1 => [
            'description' => 'LDL > 2.6 mmol/L AND HbA1c 5.7 - 6.2 %',
            'evaluator' => 'condition1',
        ],
        2 => [
            'description' => 'LDL > 2.6 mmol/L AND HbA1c >= 6.3 %',
            'evaluator' => 'condition2',
        ],
        3 => [
            'description' => 'TC > 5.2 mmol/L AND HbA1c 5.7 - 6.2 %',
            'evaluator' => 'condition3',
        ],
        4 => [
            'description' => 'TC > 5.2 mmol/L AND HbA1c >= 6.3 %',
            'evaluator' => 'condition4',
        ],
        5 => [
            'description' => 'TC > 5.2 mmol/L AND LDL > 2.6 mmol/L AND HbA1c 5.7 - 6.2 %',
            'evaluator' => 'condition5',
        ],
        6 => [
            'description' => 'TC > 5.2 mmol/L AND LDL > 2.6 mmol/L AND HbA1c >= 6.3 %',
            'evaluator' => 'condition6',
        ],
        7 => [
            'description' => 'CKD eGFR < 30 ml/min/1.73m2 AND HbA1c >= 6.3 % AND LDL > 1.4 mmol/L',
            'evaluator' => 'condition7',
        ],
        8 => [
            'description' => 'CKD eGFR 30 - 60 ml/min/1.73m2 AND HbA1c >= 6.3 % AND LDL > 1.8 mmol/L',
            'evaluator' => 'condition8',
        ],
        9 => [
            'description' => 'eGFR > 60 ml/min/1.73m2 AND HbA1c >= 6.3 % AND LDL > 2.6 mmol/L',
            'evaluator' => 'condition9',
        ],
        10 => [
            'description' => 'ALT > 120 U/L AND TC > 5.2 mmol/L AND LDL > 2.6 mmol/L',
            'evaluator' => 'condition10',
        ],
        11 => [
            'description' => 'eGFR 30 - 44 ml/min/1.73m2 AND HbA1c >= 6.3 %',
            'evaluator' => 'condition11',
        ],
        12 => [
            'description' => 'eGFR < 30 ml/min/1.73m2 AND HbA1c >= 6.3 %',
            'evaluator' => 'condition12',
        ],
        13 => [
            'description' => 'Age < 50 years AND HbA1c 6.3 - 6.5 %',
            'evaluator' => 'condition13',
        ],
        14 => [
            'description' => 'Age > 50 years AND HbA1c > 6.3 % AND eGFR < 45 ml/min/1.73m2',
            'evaluator' => 'condition14',
        ],
        15 => [
            'description' => 'Age > 30 years AND BMI > 23 kg/m2 AND HbA1c 5.7 - 6.2 %',
            'evaluator' => 'condition15',
        ],
        16 => [
            'description' => 'Age > 30 years AND BMI > 23 kg/m2 AND HbA1c >= 6.3 %',
            'evaluator' => 'condition16',
        ],
        17 => [
            'description' => 'Age > 30 years AND BMI > 23 kg/m2 AND TC > 5.2 mmol/L AND LDL > 2.6 mmol/L',
            'evaluator' => 'condition17',
        ],
        18 => [
            'description' => 'Age > 30 years AND BMI > 23 kg/m2 AND HbA1c >= 6.3 % AND TC > 5.2 mmol/L AND LDL > 2.6 mmol/L',
            'evaluator' => 'condition18',
        ],
        19 => [
            'description' => 'HbA1c 5.7 - 6.2 % AND (LDL 2.6 - 3.0 mmol/L OR TC 5.2 - 6.0 mmol/L)',
            'evaluator' => 'condition19',
        ],
        20 => [
            'description' => 'HbA1c 5.7 - 6.2 % AND BMI > 23 kg/m2',
            'evaluator' => 'condition20',
        ],
        21 => [
            'description' => 'HbA1c 5.7 - 6.2 % AND ALT 40 - 120 U/L',
            'evaluator' => 'condition21',
        ],
        22 => [
            'description' => 'HbA1c 5.7 - 6.2 % AND eGFR 45 - 60 ml/min/1.73m2',
            'evaluator' => 'condition22',
        ],
        23 => [
            'description' => 'HbA1c >= 6.3 % AND (LDL > 2.6 mmol/L OR TC > 5.2 mmol/L)',
            'evaluator' => 'condition23',
        ],
        24 => [
            'description' => 'HbA1c >= 6.3 % AND eGFR 30 - 60 ml/min/1.73m2',
            'evaluator' => 'condition24',
        ],
        25 => [
            'description' => 'ALT 40 - 120 U/L AND HbA1c 5.7 - 6.2 % AND LDL 2.6 - 3.0 mmol/L AND TC 5.2 - 6.0 mmol/L',
            'evaluator' => 'condition25',
        ],
    ];

    /**
     * Get all condition IDs
     */
    public static function getAllIds(): array
    {
        return array_keys(self::CONDITIONS);
    }

    /**
     * Get condition by ID
     */
    public static function getCondition(int $id): ?array
    {
        return self::CONDITIONS[$id] ?? null;
    }

    /**
     * Get all conditions
     */
    public static function getAll(): array
    {
        return self::CONDITIONS;
    }
}
