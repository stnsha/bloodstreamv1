<?php

namespace Database\Seeders;

use App\Models\ClinicalCondition;
use Exception;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LiverAndBoneClinicalConditionSeeder extends Seeder
{
    private const CONDITIONS = [
        102 => [
            'description' => 'ALT >50 U/L',
            'type' => 'AO',
            'add_ons' => 'Hepatitis A (HAG) + Hepatitis B Serology (HB3)',
            'evaluator' => 'condition102',
            'risk_tier' => 1,
            'criteria_count' => 1,
        ],
        103 => [
            'description' => 'AST >40 U/L',
            'type' => 'AO',
            'add_ons' => 'Hepatitis A (HAG) + Hepatitis B Serology (HB3)',
            'evaluator' => 'condition103',
            'risk_tier' => 1,
            'criteria_count' => 1,
        ],
        104 => [
            'description' => 'ALP >150 U/L',
            'type' => 'AO',
            'add_ons' => 'Hepatitis A (HAG) + Hepatitis B Serology (HB3)',
            'evaluator' => 'condition104',
            'risk_tier' => 1,
            'criteria_count' => 1,
        ],
        105 => [
            'description' => 'ALT >50 U/L AND AST >40 U/L',
            'type' => 'AO',
            'add_ons' => 'Hepatitis A (HAG) + Hepatitis B Serology (HB3)',
            'evaluator' => 'condition105',
            'risk_tier' => 2,
            'criteria_count' => 2,
        ],
        106 => [
            'description' => 'ALT >50 U/L AND ALP >150 U/L',
            'type' => 'AO',
            'add_ons' => 'Hepatitis A (HAG) + Hepatitis B Serology (HB3)',
            'evaluator' => 'condition106',
            'risk_tier' => 2,
            'criteria_count' => 2,
        ],
        107 => [
            'description' => 'AST >40 U/L AND ALP >150 U/L',
            'type' => 'AO',
            'add_ons' => 'Hepatitis A (HAG) + Hepatitis B Serology (HB3)',
            'evaluator' => 'condition107',
            'risk_tier' => 2,
            'criteria_count' => 2,
        ],
        108 => [
            'description' => 'ALT >50 U/L AND AST >40 U/L AND ALP >150 U/L',
            'type' => 'AO',
            'add_ons' => 'Hepatitis A (HAG) + Hepatitis B Serology (HB3)',
            'evaluator' => 'condition108',
            'risk_tier' => 3,
            'criteria_count' => 3,
        ],
        109 => [
            'description' => 'ALP >150 U/L AND Corrected Calcium <2.10 mmol/L',
            'type' => 'AO',
            'add_ons' => 'ALEMO Package (Vitamin D + Vitamin B12 + Magnesium)',
            'evaluator' => 'condition109',
            'risk_tier' => 2,
            'criteria_count' => 2,
        ],
        110 => [
            'description' => 'ALP >150 U/L AND Corrected Calcium <2.10 mmol/L AND Hb 100-129 g/L',
            'type' => 'AO',
            'add_ons' => 'ALEMO Package (Vitamin D + Vitamin B12 + Magnesium)',
            'evaluator' => 'condition110',
            'risk_tier' => 3,
            'criteria_count' => 3,
        ],
        111 => [
            'description' => 'ALP >150 U/L AND Phosphate <0.65 mmol/L',
            'type' => 'AO',
            'add_ons' => 'ALEMO Package (Vitamin D + Vitamin B12 + Magnesium)',
            'evaluator' => 'condition111',
            'risk_tier' => 2,
            'criteria_count' => 2,
        ],
        112 => [
            'description' => 'ALP >150 U/L AND Phosphate <0.65 mmol/L AND Hb 100-129 g/L',
            'type' => 'AO',
            'add_ons' => 'ALEMO Package (Vitamin D + Vitamin B12 + Magnesium)',
            'evaluator' => 'condition112',
            'risk_tier' => 3,
            'criteria_count' => 3,
        ],
        113 => [
            'description' => 'Corrected Calcium <2.10 mmol/L AND Hb 100-129 g/L',
            'type' => 'AO',
            'add_ons' => 'ALEMO Package (Vitamin D + Vitamin B12 + Magnesium)',
            'evaluator' => 'condition113',
            'risk_tier' => 2,
            'criteria_count' => 2,
        ],
        114 => [
            'description' => 'Phosphate <0.65 mmol/L AND Hb 100-129 g/L',
            'type' => 'AO',
            'add_ons' => 'ALEMO Package (Vitamin D + Vitamin B12 + Magnesium)',
            'evaluator' => 'condition114',
            'risk_tier' => 2,
            'criteria_count' => 2,
        ],
        115 => [
            'description' => 'Corrected Calcium <2.10 mmol/L AND Phosphate <0.65 mmol/L AND Hb 100-129 g/L',
            'type' => 'AO',
            'add_ons' => 'ALEMO Package (Vitamin D + Vitamin B12 + Magnesium)',
            'evaluator' => 'condition115',
            'risk_tier' => 3,
            'criteria_count' => 3,
        ],
        116 => [
            'description' => 'ALP >150 U/L AND Corrected Calcium <2.10 mmol/L AND Phosphate <0.65 mmol/L AND Hb 100-129 g/L',
            'type' => 'AO',
            'add_ons' => 'ALEMO Package (Vitamin D + Vitamin B12 + Magnesium)',
            'evaluator' => 'condition116',
            'risk_tier' => 3,
            'criteria_count' => 4,
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Log::info('LiverAndBoneClinicalConditionSeeder: Starting seeding');

        try {
            DB::beginTransaction();

            $seededCount = 0;

            foreach (self::CONDITIONS as $id => $data) {
                ClinicalCondition::updateOrCreate(
                    ['id' => $id],
                    [
                        'description' => $data['description'],
                        'type' => $data['type'],
                        'add_ons' => $data['add_ons'],
                        'evaluator' => $data['evaluator'],
                        'risk_tier' => $data['risk_tier'],
                        'criteria_count' => $data['criteria_count'],
                        'is_active' => true,
                    ]
                );
                $seededCount++;
            }

            DB::commit();

            ClinicalCondition::clearCache();

            Log::info('LiverAndBoneClinicalConditionSeeder: Seeding completed', [
                'total_seeded' => $seededCount,
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('LiverAndBoneClinicalConditionSeeder: Seeding failed', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
