<?php

namespace Database\Seeders;

use App\Models\ClinicalCondition;
use Exception;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AnemiaClinicalConditionSeeder extends Seeder
{
    private const CONDITIONS = [
        31 => [
            'description' => 'Hb 100-129 g/L',
            'evaluator' => 'condition31',
            'risk_tier' => 2,
            'criteria_count' => 1,
            'active_from' => '2026-06-02',
        ],
        32 => [
            'description' => 'Ferritin <30 ug/L',
            'evaluator' => 'condition32',
            'risk_tier' => 2,
            'criteria_count' => 1,
            'active_from' => '2026-06-02',
        ],
        33 => [
            'description' => 'Ferritin <15 ug/L',
            'evaluator' => 'condition33',
            'risk_tier' => 3,
            'criteria_count' => 1,
            'active_from' => '2026-06-02',
        ],
        34 => [
            'description' => 'Serum Iron <10 umol/L',
            'evaluator' => 'condition34',
            'risk_tier' => 2,
            'criteria_count' => 1,
            'active_from' => '2026-06-02',
        ],
        35 => [
            'description' => 'MCV <80 fL',
            'evaluator' => 'condition35',
            'risk_tier' => 2,
            'criteria_count' => 1,
            'active_from' => '2026-06-02',
        ],
        36 => [
            'description' => 'Hb <100 g/L',
            'evaluator' => 'condition36',
            'risk_tier' => 3,
            'criteria_count' => 1,
            'active_from' => '2026-06-02',
        ],
        37 => [
            'description' => 'MCV >100 fL',
            'evaluator' => 'condition37',
            'risk_tier' => 2,
            'criteria_count' => 1,
            'active_from' => '2026-06-02',
        ],
        38 => [
            'description' => 'Hb 100-129 g/L AND Ferritin <30 ug/L',
            'evaluator' => 'condition38',
            'risk_tier' => 1,
            'criteria_count' => 2,
            'active_from' => '2026-06-02',
        ],
        39 => [
            'description' => 'Hb 100-129 g/L AND Ferritin <15 ug/L',
            'evaluator' => 'condition39',
            'risk_tier' => 3,
            'criteria_count' => 2,
            'active_from' => '2026-06-02',
        ],
        40 => [
            'description' => 'Hb <100 g/L AND Ferritin <30 ug/L',
            'evaluator' => 'condition40',
            'risk_tier' => 3,
            'criteria_count' => 2,
            'active_from' => '2026-06-02',
        ],
        41 => [
            'description' => 'Hb <100 g/L AND Ferritin <15 ug/L',
            'evaluator' => 'condition41',
            'risk_tier' => 3,
            'criteria_count' => 2,
            'active_from' => '2026-06-02',
        ],
        42 => [
            'description' => 'Hb 100-129 g/L AND Ferritin <30 ug/L AND MCV <80 fL AND MCH <27 pg',
            'evaluator' => 'condition42',
            'risk_tier' => 1,
            'criteria_count' => 4,
            'active_from' => '2026-06-02',
        ],
        43 => [
            'description' => 'Ferritin <30 ug/L AND Serum Iron <10 umol/L',
            'evaluator' => 'condition43',
            'risk_tier' => 1,
            'criteria_count' => 2,
            'active_from' => '2026-06-02',
        ],
        44 => [
            'description' => 'Hb 100-129 g/L AND RDW >14.5% AND MCV <80 fL',
            'evaluator' => 'condition44',
            'risk_tier' => 1,
            'criteria_count' => 3,
            'active_from' => '2026-06-02',
        ],
        45 => [
            'description' => 'MCV <80 fL AND RCC >5.0 x10^12/L',
            'evaluator' => 'condition45',
            'risk_tier' => 3,
            'criteria_count' => 2,
            'active_from' => '2026-06-02',
        ],
        46 => [
            'description' => 'Hb 100-129 g/L AND Serum Iron <10 umol/L',
            'evaluator' => 'condition46',
            'risk_tier' => 1,
            'criteria_count' => 2,
            'active_from' => '2026-06-02',
        ],
        47 => [
            'description' => 'Hb 100-129 g/L AND MCV <80 fL',
            'evaluator' => 'condition47',
            'risk_tier' => 1,
            'criteria_count' => 2,
            'active_from' => '2026-06-02',
        ],
        48 => [
            'description' => 'Hb 100-129 g/L AND MCH <27 pg',
            'evaluator' => 'condition48',
            'risk_tier' => 1,
            'criteria_count' => 2,
            'active_from' => '2026-06-02',
        ],
        49 => [
            'description' => 'Hb 100-129 g/L AND MCHC <320 g/L AND Ferritin <30 ug/L AND Serum Iron <10 umol/L',
            'evaluator' => 'condition49',
            'risk_tier' => 2,
            'criteria_count' => 4,
            'active_from' => '2026-06-02',
        ],
        50 => [
            'description' => 'Hb 100-129 g/L AND RDW >14.5%',
            'evaluator' => 'condition50',
            'risk_tier' => 1,
            'criteria_count' => 2,
            'active_from' => '2026-06-02',
        ],
        51 => [
            'description' => 'Hb 100-129 g/L AND PCV/HCT <0.36 L/L AND Female',
            'evaluator' => 'condition51',
            'risk_tier' => 1,
            'criteria_count' => 3,
            'active_from' => '2026-06-02',
        ],
        52 => [
            'description' => 'Hb 100-129 g/L AND PCV/HCT <0.40 L/L AND Male',
            'evaluator' => 'condition52',
            'risk_tier' => 1,
            'criteria_count' => 3,
            'active_from' => '2026-06-02',
        ],
        53 => [
            'description' => 'Hb 100-129 g/L AND RCC <3.9 x10^12/L AND Female',
            'evaluator' => 'condition53',
            'risk_tier' => 1,
            'criteria_count' => 3,
            'active_from' => '2026-06-02',
        ],
        54 => [
            'description' => 'Hb 100-129 g/L AND RCC <4.3 x10^12/L AND Male',
            'evaluator' => 'condition54',
            'risk_tier' => 1,
            'criteria_count' => 3,
            'active_from' => '2026-06-02',
        ],
        55 => [
            'description' => 'Serum Iron <10 umol/L AND MCH <27 pg',
            'evaluator' => 'condition55',
            'risk_tier' => 1,
            'criteria_count' => 2,
            'active_from' => '2026-06-02',
        ],
        56 => [
            'description' => 'Serum Iron <10 umol/L AND MCV <80 fL',
            'evaluator' => 'condition56',
            'risk_tier' => 1,
            'criteria_count' => 2,
            'active_from' => '2026-06-02',
        ],
        57 => [
            'description' => 'Serum Iron <10 umol/L AND MCHC <320 g/L',
            'evaluator' => 'condition57',
            'risk_tier' => 1,
            'criteria_count' => 2,
            'active_from' => '2026-06-02',
        ],
        58 => [
            'description' => 'Serum Iron <10 umol/L AND RDW >14.5%',
            'evaluator' => 'condition58',
            'risk_tier' => 1,
            'criteria_count' => 2,
            'active_from' => '2026-06-02',
        ],
        59 => [
            'description' => 'Serum Iron <10 umol/L AND PCV/HCT <0.36 L/L AND Female',
            'evaluator' => 'condition59',
            'risk_tier' => 1,
            'criteria_count' => 3,
            'active_from' => '2026-06-02',
        ],
        60 => [
            'description' => 'Serum Iron <10 umol/L AND PCV/HCT <0.40 L/L AND Male',
            'evaluator' => 'condition60',
            'risk_tier' => 1,
            'criteria_count' => 3,
            'active_from' => '2026-06-02',
        ],
        61 => [
            'description' => 'Serum Iron <10 umol/L AND RCC <3.9 x10^12/L AND Female',
            'evaluator' => 'condition61',
            'risk_tier' => 1,
            'criteria_count' => 3,
            'active_from' => '2026-06-02',
        ],
        62 => [
            'description' => 'Serum Iron <10 umol/L AND RCC <4.3 x10^12/L AND Male',
            'evaluator' => 'condition62',
            'risk_tier' => 1,
            'criteria_count' => 3,
            'active_from' => '2026-06-02',
        ],
        63 => [
            'description' => 'Serum Iron <10 umol/L AND Ferritin <30 ug/L',
            'evaluator' => 'condition63',
            'risk_tier' => 1,
            'criteria_count' => 2,
            'active_from' => '2026-06-02',
        ],
        64 => [
            'description' => 'Hb 100-129 g/L AND Serum Iron <10 umol/L AND MCH <27 pg',
            'evaluator' => 'condition64',
            'risk_tier' => 1,
            'criteria_count' => 3,
            'active_from' => '2026-06-02',
        ],
        65 => [
            'description' => 'Hb 100-129 g/L AND PCV/HCT <0.36 L/L AND Female AND Serum Iron <10 umol/L',
            'evaluator' => 'condition65',
            'risk_tier' => 1,
            'criteria_count' => 4,
            'active_from' => '2026-06-02',
        ],
        66 => [
            'description' => 'Hb 100-129 g/L AND PCV/HCT <0.40 L/L AND Male AND Serum Iron <10 umol/L',
            'evaluator' => 'condition66',
            'risk_tier' => 1,
            'criteria_count' => 4,
            'active_from' => '2026-06-02',
        ],
        67 => [
            'description' => 'Hb 100-129 g/L AND Serum Iron <10 umol/L AND MCV <80 fL AND MCH <27 pg AND MCHC <320 g/L AND RDW >14.5% AND PCV/HCT <0.36 L/L AND Female AND RCC <3.9 x10^12/L AND Ferritin <30 ug/L',
            'evaluator' => 'condition67',
            'risk_tier' => 3,
            'criteria_count' => 10,
            'active_from' => '2026-06-02',
        ],
        68 => [
            'description' => 'Hb 100-129 g/L AND Serum Iron <10 umol/L AND MCV <80 fL AND MCH <27 pg AND MCHC <320 g/L AND RDW >14.5% AND PCV/HCT <0.40 L/L AND Male AND RCC <4.3 x10^12/L AND Ferritin <30 ug/L',
            'evaluator' => 'condition68',
            'risk_tier' => 3,
            'criteria_count' => 10,
            'active_from' => '2026-06-02',
        ],
        69 => [
            'description' => 'Hb 100-129 g/L AND Serum Iron <10 umol/L AND MCV <80 fL AND MCH <27 pg AND MCHC <320 g/L AND RDW >14.5% AND PCV/HCT <0.36 L/L AND Female AND RCC <3.9 x10^12/L',
            'evaluator' => 'condition69',
            'risk_tier' => 3,
            'criteria_count' => 9,
            'active_from' => '2026-06-02',
        ],
        70 => [
            'description' => 'Hb 100-129 g/L AND Serum Iron <10 umol/L AND MCV <80 fL AND MCH <27 pg AND MCHC <320 g/L AND RDW >14.5% AND PCV/HCT <0.40 L/L AND Male AND RCC <4.3 x10^12/L',
            'evaluator' => 'condition70',
            'risk_tier' => 3,
            'criteria_count' => 9,
            'active_from' => '2026-06-02',
        ],
        71 => [
            'description' => 'Hb 100-129 g/L AND MCV <80 fL AND MCH <27 pg AND MCHC <320 g/L AND RDW >14.5% AND PCV/HCT <0.36 L/L AND Female AND RCC <3.9 x10^12/L',
            'evaluator' => 'condition71',
            'risk_tier' => 2,
            'criteria_count' => 8,
            'active_from' => '2026-06-02',
        ],
        72 => [
            'description' => 'Hb 100-129 g/L AND MCV <80 fL AND MCH <27 pg AND MCHC <320 g/L AND RDW >14.5% AND PCV/HCT <0.40 L/L AND Male AND RCC <4.3 x10^12/L',
            'evaluator' => 'condition72',
            'risk_tier' => 2,
            'criteria_count' => 8,
            'active_from' => '2026-06-02',
        ],
        73 => [
            'description' => 'Ferritin <15 ug/L AND MCV <80 fL AND MCH <27 pg',
            'evaluator' => 'condition73',
            'risk_tier' => 3,
            'criteria_count' => 3,
            'active_from' => '2026-06-02',
        ],
        74 => [
            'description' => 'Ferritin <30 ug/L AND RDW >14.5%',
            'evaluator' => 'condition74',
            'risk_tier' => 1,
            'criteria_count' => 2,
            'active_from' => '2026-06-02',
        ],
        75 => [
            'description' => 'Hb 100-129 g/L AND MCV <80 fL AND RCC >5.0 x10^12/L',
            'evaluator' => 'condition75',
            'risk_tier' => 2,
            'criteria_count' => 3,
            'active_from' => '2026-06-02',
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Log::info('AnemiaClinicalConditionSeeder: Starting seeding');

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
                        'active_from' => $data['active_from'],
                    ]
                );
                $seededCount++;
            }

            DB::commit();

            ClinicalCondition::clearCache();

            Log::info('AnemiaClinicalConditionSeeder: Seeding completed', [
                'total_seeded' => $seededCount,
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('AnemiaClinicalConditionSeeder: Seeding failed', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
