<?php

namespace Database\Seeders;

use App\Models\ClinicalCondition;
use Exception;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SeptemberClinicalConditionSeeder extends Seeder
{
    /**
     * Clinical conditions added September 2026.
     *
     * risk_tier: 3 = HIGH, 2 = MEDIUM, 1 = LOW.
     * type: 'CC' = clinical condition, 'CC + AO' = clinical condition plus add-on.
     */
    private const ACTIVE_FROM = '2026-09-08';

    private const CONDITIONS = [
        117 => [
            'description' => 'LDL > 3.39 mmol/L AND HbA1c >= 6.3 %',
            'type' => 'CC',
            'evaluator' => 'condition117',
            'risk_tier' => 3,
            'criteria_count' => 2,
        ],
        118 => [
            'description' => 'TC > 5.9 mmol/L AND HbA1c >= 6.3 %',
            'type' => 'CC',
            'evaluator' => 'condition118',
            'risk_tier' => 3,
            'criteria_count' => 2,
        ],
        119 => [
            'description' => 'TC > 5.9 mmol/L AND LDL-C > 3.39 mmol/L AND HbA1c >= 6.3 %',
            'type' => 'CC',
            'evaluator' => 'condition119',
            'risk_tier' => 3,
            'criteria_count' => 3,
        ],
        120 => [
            'description' => 'ALT > 120 U/L (after statin initiation) AND TC > 5.9 mmol/L AND LDL-C > 3.39 mmol/L',
            'type' => 'CC + AO',
            'evaluator' => 'condition120',
            'risk_tier' => 2,
            'criteria_count' => 3,
        ],
        121 => [
            'description' => 'Age > 30 years old AND BMI > 23 kg/m2 AND TC > 5.9 mmol/L AND LDL-C > 3.39 mmol/L',
            'type' => 'CC',
            'evaluator' => 'condition121',
            'risk_tier' => 2,
            'criteria_count' => 4,
        ],
        122 => [
            'description' => 'Age > 30 years old AND BMI > 23 kg/m2 AND HbA1c >= 6.3 % AND TC > 5.9 mmol/L AND LDL-C > 3.39 mmol/L',
            'type' => 'CC',
            'evaluator' => 'condition122',
            'risk_tier' => 3,
            'criteria_count' => 5,
        ],
        123 => [
            'description' => 'HbA1c >= 6.3 % AND ALT mildly elevated (40-120 U/L)',
            'type' => 'CC + AO',
            'evaluator' => 'condition123',
            'risk_tier' => 1,
            'criteria_count' => 2,
        ],
        124 => [
            'description' => 'ALT mildly elevated (40-120 U/L) AND HbA1c >= 6.1 % AND LDL-C > 3.39 mmol/L AND TC > 5.9 mmol/L',
            'type' => 'CC',
            'evaluator' => 'condition124',
            'risk_tier' => 2,
            'criteria_count' => 4,
        ],
        125 => [
            'description' => 'LDL-C > 3.39 mmol/L AND TC > 5.9 mmol/L',
            'type' => 'CC',
            'evaluator' => 'condition125',
            'risk_tier' => 2,
            'criteria_count' => 2,
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Log::info('SeptemberClinicalConditionSeeder: Starting seeding', [
            'condition_ids' => array_keys(self::CONDITIONS),
            'active_from' => self::ACTIVE_FROM,
        ]);

        try {
            DB::beginTransaction();

            $seededCount = 0;

            foreach (self::CONDITIONS as $id => $data) {
                ClinicalCondition::updateOrCreate(
                    ['id' => $id],
                    [
                        'description' => $data['description'],
                        'type' => $data['type'],
                        'evaluator' => $data['evaluator'],
                        'risk_tier' => $data['risk_tier'],
                        'criteria_count' => $data['criteria_count'],
                        'is_active' => true,
                        'active_from' => self::ACTIVE_FROM,
                    ]
                );
                $seededCount++;
            }

            DB::commit();

            ClinicalCondition::clearCache();

            Log::info('SeptemberClinicalConditionSeeder: Seeding completed', [
                'total_seeded' => $seededCount,
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('SeptemberClinicalConditionSeeder: Seeding failed', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
