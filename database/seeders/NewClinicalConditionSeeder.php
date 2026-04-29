<?php

namespace Database\Seeders;

use App\Models\ClinicalCondition;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NewClinicalConditionSeeder extends Seeder
{
    private const CONDITIONS = [
            27 => [
                'description' => 'Total Cholesterol (TC) > 5.2 mmol/L',
                'evaluator' => 'condition27',
                'risk_tier' => 2,
                'criteria_count' => 1,
            ],
            28 => [
                'description' => 'LDL > 2.6 mmol/L',
                'evaluator' => 'condition28',
                'risk_tier' => 2,
                'criteria_count' => 1,
            ],
            29 => [
                'description' => 'LDL-C > 2.6 mmol/L AND TC > 5.2 mmol/L',
                'evaluator' => 'condition29',
                'risk_tier' => 2,
                'criteria_count' => 2,
            ],
            30 => [
                'description' => 'HbA1c >= 6.3 %',
                'evaluator' => 'condition30',
                'risk_tier' => 2,
                'criteria_count' => 1,
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

