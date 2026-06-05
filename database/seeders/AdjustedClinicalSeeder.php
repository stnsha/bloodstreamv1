<?php

namespace Database\Seeders;

use App\Models\ClinicalCondition;
use Exception;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdjustedClinicalSeeder extends Seeder
{
    private const CONDITIONS = [
            76 => [
                'description' => 'TC > 5.9 mmol/L',
                'evaluator' => 'condition76',
                'risk_tier' => 2,
                'criteria_count' => 1,
                'active_from' => '2026-06-05',
            ],
            77 => [
                'description' => 'LDL > 3.39 mmol/L',
                'evaluator' => 'condition77',
                'risk_tier' => 2,
                'criteria_count' => 1,
                'active_from' => '2026-06-05',
            ],
            78 => [
                'description' => 'LDL > 3.0 mmol/L AND TC > 5.5 mmol/L',
                'evaluator' => 'condition78',
                'risk_tier' => 2,
                'criteria_count' => 2,
                'active_from' => '2026-06-05',
            ],
            79 => [
                'description' => 'HbA1c >= 6.1 %',
                'evaluator' => 'condition79',
                'risk_tier' => 2,
                'criteria_count' => 1,
                'active_from' => '2026-06-05',
            ],
            80 => [
                'description' => 'Hb 100-115 g/L',
                'evaluator' => 'condition80',
                'risk_tier' => 2,
                'criteria_count' => 1,
                'active_from' => '2026-06-05',
            ],
            81 => [
                'description' => 'Ferritin <20 ug/L',
                'evaluator' => 'condition81',
                'risk_tier' => 2,
                'criteria_count' => 1,
                'active_from' => '2026-06-05',
            ],
        ];
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Log::info('AdjustedClinicalSeeder: Starting seeding');

        try {
            DB::beginTransaction();

            $seededCount = 0;

            foreach (self::CONDITIONS as $id => $data) {
                ClinicalCondition::updateOrCreate(
                    ['id' => $id],
                    [
                        'description'    => $data['description'],
                        'evaluator'      => $data['evaluator'],
                        'risk_tier'      => $data['risk_tier'],
                        'criteria_count' => $data['criteria_count'],
                        'is_active'      => true,
                        'active_from'    => $data['active_from'],
                    ]
                );
                $seededCount++;
            }

            DB::commit();

            ClinicalCondition::clearCache();

            Log::info('AdjustedClinicalSeeder: Seeding completed', [
                'total_seeded' => $seededCount,
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('AdjustedClinicalSeeder: Seeding failed', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
