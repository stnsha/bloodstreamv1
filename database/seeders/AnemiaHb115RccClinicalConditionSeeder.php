<?php

namespace Database\Seeders;

use App\Models\ClinicalCondition;
use Exception;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AnemiaHb115RccClinicalConditionSeeder extends Seeder
{
    /**
     * Follow-up to AnemiaHb115ClinicalConditionSeeder: narrows condition 75
     * (Hb 100-129 g/L AND MCV <80 fL AND RCC >5.0 x10^12/L) to Hb 100-115 g/L.
     *
     * The new condition mirrors the type, risk_tier and criteria_count of the
     * condition it replaces. The replaced condition is marked inactive.
     *
     * risk_tier: 3 = HIGH, 2 = MEDIUM, 1 = LOW.
     * type: 'CC' = clinical condition, 'CC + AO' = clinical condition plus add-on.
     */
    private const ACTIVE_FROM = '2026-09-29';

    /**
     * Replaced condition ID => new condition ID.
     */
    private const REPLACEMENTS = [
        75 => 140,
    ];

    private const CONDITIONS = [
        140 => [
            'description' => 'Hb 100-115 g/L AND MCV <80 fL AND RCC >5.0 x10^12/L',
            'type' => 'CC + AO',
            'evaluator' => 'condition140',
            'risk_tier' => 2,
            'criteria_count' => 3,
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Log::info('AnemiaHb115RccClinicalConditionSeeder: Starting seeding', [
            'new_condition_ids' => array_keys(self::CONDITIONS),
            'deactivated_condition_ids' => array_keys(self::REPLACEMENTS),
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

            $deactivatedCount = ClinicalCondition::whereIn('id', array_keys(self::REPLACEMENTS))
                ->update(['is_active' => false]);

            DB::commit();

            ClinicalCondition::clearCache();

            Log::info('AnemiaHb115RccClinicalConditionSeeder: Seeding completed', [
                'total_seeded' => $seededCount,
                'total_deactivated' => $deactivatedCount,
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('AnemiaHb115RccClinicalConditionSeeder: Seeding failed', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
