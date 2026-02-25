<?php

namespace Database\Seeders;

use App\Models\ClinicalCondition;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClinicalConditionSeeder extends Seeder
{
    /**
     * All clinical conditions data.
     */
    private const CONDITIONS = [ 
        1 => [
            'description' => 'Healthy',
            'evaluator' => 'healthy',
            'risk_tier' => 0,
            'criteria_count' => 0,
        ],
        2 => [
            'description' => 'LDL > 2.6 mmol/L AND HbA1c 5.7 - 6.2 %',
            'evaluator' => 'condition1',
            'risk_tier' => 1,
            'criteria_count' => 2,
        ],
        3 => [
            'description' => 'LDL > 2.6 mmol/L AND HbA1c >= 6.3 %',
            'evaluator' => 'condition2',
            'risk_tier' => 3,
            'criteria_count' => 2,
        ],
        4 => [
            'description' => 'TC > 5.2 mmol/L AND HbA1c 5.7 - 6.2 %',
            'evaluator' => 'condition3',
            'risk_tier' => 1,
            'criteria_count' => 2,
        ],
        5 => [
            'description' => 'TC > 5.2 mmol/L AND HbA1c >= 6.3 %',
            'evaluator' => 'condition4',
            'risk_tier' => 3,
            'criteria_count' => 2,
        ],
        6 => [
            'description' => 'TC > 5.2 mmol/L AND LDL > 2.6 mmol/L AND HbA1c 5.7 - 6.2 %',
            'evaluator' => 'condition5',
            'risk_tier' => 2,
            'criteria_count' => 3,
        ],
        7 => [
            'description' => 'TC > 5.2 mmol/L AND LDL > 2.6 mmol/L AND HbA1c >= 6.3 %',
            'evaluator' => 'condition6',
            'risk_tier' => 3,
            'criteria_count' => 3,
        ],
        8 => [
            'description' => 'CKD eGFR < 30 ml/min/1.73m2 AND HbA1c >= 6.3 % AND LDL > 1.4 mmol/L',
            'evaluator' => 'condition7',
            'risk_tier' => 3,
            'criteria_count' => 3,
        ],
        9 => [
            'description' => 'CKD eGFR 30 - 60 ml/min/1.73m2 AND HbA1c >= 6.3 % AND LDL > 1.8 mmol/L',
            'evaluator' => 'condition8',
            'risk_tier' => 2,
            'criteria_count' => 3,
        ],
        10 => [
            'description' => 'eGFR > 60 ml/min/1.73m2 AND HbA1c >= 6.3 % AND LDL > 2.6 mmol/L',
            'evaluator' => 'condition9',
            'risk_tier' => 1,
            'criteria_count' => 3,
        ],
        11 => [
            'description' => 'ALT > 120 U/L AND TC > 5.2 mmol/L AND LDL > 2.6 mmol/L',
            'evaluator' => 'condition10',
            'risk_tier' => 2,
            'criteria_count' => 3,
        ],
        12 => [
            'description' => 'eGFR 30 - 44 ml/min/1.73m2 AND HbA1c >= 6.3 %',
            'evaluator' => 'condition11',
            'risk_tier' => 2,
            'criteria_count' => 2,
        ],
        13 => [
            'description' => 'eGFR < 30 ml/min/1.73m2 AND HbA1c >= 6.3 %',
            'evaluator' => 'condition12',
            'risk_tier' => 3,
            'criteria_count' => 2,
        ],
        14 => [
            'description' => 'Age < 50 years AND HbA1c 6.3 - 6.5 %',
            'evaluator' => 'condition13',
            'risk_tier' => 1,
            'criteria_count' => 2,
        ],
        15 => [
            'description' => 'Age > 50 years AND HbA1c > 6.3 % AND eGFR < 45 ml/min/1.73m2',
            'evaluator' => 'condition14',
            'risk_tier' => 3,
            'criteria_count' => 3,
        ],
        16 => [
            'description' => 'Age > 30 years AND BMI > 23 kg/m2 AND HbA1c 5.7 - 6.2 %',
            'evaluator' => 'condition15', 
            'risk_tier' => 1,
            'criteria_count' => 3,
        ],
        17 => [
            'description' => 'Age > 30 years AND BMI > 23 kg/m2 AND HbA1c >= 6.3 %',
            'evaluator' => 'condition16',
            'risk_tier' => 2,
            'criteria_count' => 3,
        ],
        18 => [
            'description' => 'Age > 30 years AND BMI > 23 kg/m2 AND TC > 5.2 mmol/L AND LDL > 2.6 mmol/L',
            'evaluator' => 'condition17',
            'risk_tier' => 2,
            'criteria_count' => 4,
        ],
        19 => [
            'description' => 'Age > 30 years AND BMI > 23 kg/m2 AND HbA1c >= 6.3 % AND TC > 5.2 mmol/L AND LDL > 2.6 mmol/L',
            'evaluator' => 'condition18',
            'risk_tier' => 3,
            'criteria_count' => 5,
        ],
        20 => [
            'description' => 'HbA1c 5.7 - 6.2 % AND (LDL 2.6 - 3.0 mmol/L OR TC 5.2 - 6.0 mmol/L)',
            'evaluator' => 'condition19',
            'risk_tier' => 1,
            'criteria_count' => 2,
        ],
        21 => [
            'description' => 'HbA1c 5.7 - 6.2 % AND BMI > 23 kg/m2',
            'evaluator' => 'condition20',
            'risk_tier' => 1,
            'criteria_count' => 2,
        ],
        22 => [
            'description' => 'HbA1c 5.7 - 6.2 % AND ALT 40 - 120 U/L',
            'evaluator' => 'condition21',
            'risk_tier' => 1,
            'criteria_count' => 2,
        ],
        23 => [
            'description' => 'HbA1c 5.7 - 6.2 % AND eGFR 45 - 60 ml/min/1.73m2',
            'evaluator' => 'condition22',
            'risk_tier' => 2,
            'criteria_count' => 2,
        ],
        24 => [
            'description' => 'HbA1c >= 6.3 % AND (LDL > 2.6 mmol/L OR TC > 5.2 mmol/L)',
            'evaluator' => 'condition23',
            'risk_tier' => 3,
            'criteria_count' => 2,
        ],
        25 => [
            'description' => 'HbA1c >= 6.3 % AND eGFR 30 - 60 ml/min/1.73m2',
            'evaluator' => 'condition24',
            'risk_tier' => 2,
            'criteria_count' => 2,
        ],
        26 => [
            'description' => 'ALT 40 - 120 U/L AND HbA1c 5.7 - 6.2 % AND LDL 2.6 - 3.0 mmol/L AND TC 5.2 - 6.0 mmol/L',
            'evaluator' => 'condition25',
            'risk_tier' => 2,
            'criteria_count' => 4,
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Log::info('ClinicalConditionSeeder: Starting seeding');

        try {
            DB::beginTransaction();

            $seededCount = 0;

            foreach (self::CONDITIONS as $id => $data) {
                ClinicalCondition::updateOrCreate(
                    ['id' => $id],
                    [
                        'description' => $data['description'],
                        'evaluator' => $data['evaluator'],
                        'risk_tier' => $data['risk_tier'],
                        'criteria_count' => $data['criteria_count'],
                        'is_active' => true,
                    ]
                );
                $seededCount++;
            }

            DB::commit();

            // Clear cache after seeding
            ClinicalCondition::clearCache();

            Log::info('ClinicalConditionSeeder: Seeding completed', [
                'total_seeded' => $seededCount,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('ClinicalConditionSeeder: Seeding failed', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
