<?php

namespace Database\Seeders;

use App\Models\ClinicalCondition;
use Exception;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AnemiaHb115ClinicalConditionSeeder extends Seeder
{
    /**
     * Anemia conditions with the Hb band narrowed from 100-129 g/L to 100-115 g/L.
     *
     * Each new condition mirrors the type, risk_tier and criteria_count of the
     * Hb 100-129 g/L condition it replaces. The replaced conditions are marked inactive.
     *
     * risk_tier: 3 = HIGH, 2 = MEDIUM, 1 = LOW.
     * type: 'CC' = clinical condition, 'CC + AO' = clinical condition plus add-on.
     */
    private const ACTIVE_FROM = '2026-09-29';

    /**
     * Replaced condition ID => new condition ID.
     */
    private const REPLACEMENTS = [
        38 => 126,
        42 => 127,
        44 => 128,
        71 => 129,
        72 => 130,
        83 => 131,
        84 => 132,
        94 => 133,
        95 => 134,
        96 => 135,
        97 => 136,
        98 => 137,
        99 => 138,
        100 => 139,
    ];

    private const CONDITIONS = [
        126 => [
            'description' => 'Hb 100-115 g/L AND Ferritin <30 ug/L',
            'type' => 'CC + AO',
            'evaluator' => 'condition126',
            'risk_tier' => 1,
            'criteria_count' => 2,
        ],
        127 => [
            'description' => 'Hb 100-115 g/L AND Ferritin <30 ug/L AND MCV <80 fL AND MCH <27 pg',
            'type' => 'CC + AO',
            'evaluator' => 'condition127',
            'risk_tier' => 1,
            'criteria_count' => 4,
        ],
        128 => [
            'description' => 'Hb 100-115 g/L AND RDW >14.5% AND MCV <80 fL',
            'type' => 'CC + AO',
            'evaluator' => 'condition128',
            'risk_tier' => 1,
            'criteria_count' => 3,
        ],
        129 => [
            'description' => 'Hb 100-115 g/L AND MCV <80 fL AND MCH <27 pg AND MCHC <320 g/L AND RDW >14.5% AND PCV/HCT <0.36 L/L AND Female AND RCC <3.9 x10^12/L',
            'type' => 'CC + AO',
            'evaluator' => 'condition129',
            'risk_tier' => 2,
            'criteria_count' => 8,
        ],
        130 => [
            'description' => 'Hb 100-115 g/L AND MCV <80 fL AND MCH <27 pg AND MCHC <320 g/L AND RDW >14.5% AND PCV/HCT <0.40 L/L AND Male AND RCC <4.3 x10^12/L',
            'type' => 'CC + AO',
            'evaluator' => 'condition130',
            'risk_tier' => 2,
            'criteria_count' => 8,
        ],
        131 => [
            'description' => 'Hb 100-115 g/L AND Serum Iron <9 umol/L',
            'type' => 'CC + AO',
            'evaluator' => 'condition131',
            'risk_tier' => 1,
            'criteria_count' => 2,
        ],
        132 => [
            'description' => 'Hb 100-115 g/L AND MCHC <320 g/L AND Ferritin <30 ug/L AND Serum Iron <9 umol/L',
            'type' => 'CC + AO',
            'evaluator' => 'condition132',
            'risk_tier' => 2,
            'criteria_count' => 4,
        ],
        133 => [
            'description' => 'Hb 100-115 g/L AND Serum Iron <9 umol/L AND MCH <27 pg',
            'type' => 'CC + AO',
            'evaluator' => 'condition133',
            'risk_tier' => 1,
            'criteria_count' => 3,
        ],
        134 => [
            'description' => 'Hb 100-115 g/L AND PCV/HCT <0.36 L/L AND Female AND Serum Iron <9 umol/L',
            'type' => 'CC + AO',
            'evaluator' => 'condition134',
            'risk_tier' => 1,
            'criteria_count' => 4,
        ],
        135 => [
            'description' => 'Hb 100-115 g/L AND PCV/HCT <0.40 L/L AND Male AND Serum Iron <9 umol/L',
            'type' => 'CC + AO',
            'evaluator' => 'condition135',
            'risk_tier' => 1,
            'criteria_count' => 4,
        ],
        136 => [
            'description' => 'Hb 100-115 g/L AND Serum Iron <9 umol/L AND MCV <80 fL AND MCH <27 pg AND MCHC <320 g/L AND RDW >14.5% AND PCV/HCT <0.36 L/L AND Female AND RCC <3.9 x10^12/L AND Ferritin <30 ug/L',
            'type' => 'CC + AO',
            'evaluator' => 'condition136',
            'risk_tier' => 3,
            'criteria_count' => 10,
        ],
        137 => [
            'description' => 'Hb 100-115 g/L AND Serum Iron <9 umol/L AND MCV <80 fL AND MCH <27 pg AND MCHC <320 g/L AND RDW >14.5% AND PCV/HCT <0.40 L/L AND Male AND RCC <4.3 x10^12/L AND Ferritin <30 ug/L',
            'type' => 'CC + AO',
            'evaluator' => 'condition137',
            'risk_tier' => 3,
            'criteria_count' => 10,
        ],
        138 => [
            'description' => 'Hb 100-115 g/L AND Serum Iron <9 umol/L AND MCV <80 fL AND MCH <27 pg AND MCHC <320 g/L AND RDW >14.5% AND PCV/HCT <0.36 L/L AND Female AND RCC <3.9 x10^12/L',
            'type' => 'CC + AO',
            'evaluator' => 'condition138',
            'risk_tier' => 3,
            'criteria_count' => 9,
        ],
        139 => [
            'description' => 'Hb 100-115 g/L AND Serum Iron <9 umol/L AND MCV <80 fL AND MCH <27 pg AND MCHC <320 g/L AND RDW >14.5% AND PCV/HCT <0.40 L/L AND Male AND RCC <4.3 x10^12/L',
            'type' => 'CC + AO',
            'evaluator' => 'condition139',
            'risk_tier' => 3,
            'criteria_count' => 9,
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Log::info('AnemiaHb115ClinicalConditionSeeder: Starting seeding', [
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

            Log::info('AnemiaHb115ClinicalConditionSeeder: Seeding completed', [
                'total_seeded' => $seededCount,
                'total_deactivated' => $deactivatedCount,
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('AnemiaHb115ClinicalConditionSeeder: Seeding failed', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
