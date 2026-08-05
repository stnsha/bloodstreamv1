<?php

namespace Database\Seeders;

use App\Models\ClinicalCondition;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\QueryException;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SerumClinicalSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Condition definitions, keyed by evaluator method name (App\Services\ConditionEvaluatorService).
     * No 'id' here on purpose - ids are assigned automatically in run() to avoid colliding with
     * ids already owned by other clinical condition seeders.
     */
    private const CONDITIONS = [
        'condition82' => [
            'description' => 'Ferritin <30 ug/L AND Serum Iron <9 umol/L',
            'risk_tier' => 1,
            'criteria_count' => 2,
            'active_from' => '2026-08-05',
        ],
        'condition83' => [
            'description' => 'Hb 100-129 g/L AND Serum Iron <9 umol/L',
            'risk_tier' => 1,
            'criteria_count' => 2,
            'active_from' => '2026-08-05',
        ],
        'condition84' => [
            'description' => 'Hb 100-129 g/L AND MCHC <320 g/L AND Ferritin <30 ug/L AND Serum Iron <9 umol/L',
            'risk_tier' => 2,
            'criteria_count' => 4,
            'active_from' => '2026-08-05',
        ],
        'condition85' => [
            'description' => 'Serum Iron <9 umol/L AND MCH <27 pg',
            'risk_tier' => 1,
            'criteria_count' => 2,
            'active_from' => '2026-08-05',
        ],
        'condition86' => [
            'description' => 'Serum Iron <9 umol/L AND MCV <80 fL',
            'risk_tier' => 1,
            'criteria_count' => 2,
            'active_from' => '2026-08-05',
        ],
        'condition87' => [
            'description' => 'Serum Iron <9 umol/L AND MCHC <320 g/L',
            'risk_tier' => 1,
            'criteria_count' => 2,
            'active_from' => '2026-08-05',
        ],
        'condition88' => [
            'description' => 'Serum Iron <9 umol/L AND RDW >14.5%',
            'risk_tier' => 1,
            'criteria_count' => 2,
            'active_from' => '2026-08-05',
        ],
        'condition89' => [
            'description' => 'Serum Iron <9 umol/L AND PCV/HCT <0.36 L/L AND Female',
            'risk_tier' => 1,
            'criteria_count' => 3,
            'active_from' => '2026-08-05',
        ],
        'condition90' => [
            'description' => 'Serum Iron <9 umol/L AND PCV/HCT <0.40 L/L AND Male',
            'risk_tier' => 1,
            'criteria_count' => 3,
            'active_from' => '2026-08-05',
        ],
        'condition91' => [
            'description' => 'Serum Iron <9 umol/L AND RCC <3.9 x10^12/L AND Female',
            'risk_tier' => 1,
            'criteria_count' => 3,
            'active_from' => '2026-08-05',
        ],
        'condition92' => [
            'description' => 'Serum Iron <9 umol/L AND RCC <4.3 x10^12/L AND Male',
            'risk_tier' => 1,
            'criteria_count' => 3,
            'active_from' => '2026-08-05',
        ],
        'condition93' => [
            'description' => 'Serum Iron <9 umol/L AND Ferritin <30 ug/L',
            'risk_tier' => 1,
            'criteria_count' => 2,
            'active_from' => '2026-08-05',
        ],
        'condition94' => [
            'description' => 'Hb 100-129 g/L AND Serum Iron <9 umol/L AND MCH <27 pg',
            'risk_tier' => 1,
            'criteria_count' => 3,
            'active_from' => '2026-08-05',
        ],
        'condition95' => [
            'description' => 'Hb 100-129 g/L AND PCV/HCT <0.36 L/L AND Female AND Serum Iron <9 umol/L',
            'risk_tier' => 1,
            'criteria_count' => 4,
            'active_from' => '2026-08-05',
        ],
        'condition96' => [
            'description' => 'Hb 100-129 g/L AND PCV/HCT <0.40 L/L AND Male AND Serum Iron <9 umol/L',
            'risk_tier' => 1,
            'criteria_count' => 4,
            'active_from' => '2026-08-05',
        ],
        'condition97' => [
            'description' => 'Hb 100-129 g/L AND Serum Iron <9 umol/L AND MCV <80 fL AND MCH <27 pg AND MCHC <320 g/L AND RDW >14.5% AND PCV/HCT <0.36 L/L AND Female AND RCC <3.9 x10^12/L AND Ferritin <30 ug/L',
            'risk_tier' => 3,
            'criteria_count' => 10,
            'active_from' => '2026-08-05',
        ],
        'condition98' => [
            'description' => 'Hb 100-129 g/L AND Serum Iron <9 umol/L AND MCV <80 fL AND MCH <27 pg AND MCHC <320 g/L AND RDW >14.5% AND PCV/HCT <0.40 L/L AND Male AND RCC <4.3 x10^12/L AND Ferritin <30 ug/L',
            'risk_tier' => 3,
            'criteria_count' => 10,
            'active_from' => '2026-08-05',
        ],
        'condition99' => [
            'description' => 'Hb 100-129 g/L AND Serum Iron <9 umol/L AND MCV <80 fL AND MCH <27 pg AND MCHC <320 g/L AND RDW >14.5% AND PCV/HCT <0.36 L/L AND Female AND RCC <3.9 x10^12/L',
            'risk_tier' => 3,
            'criteria_count' => 9,
            'active_from' => '2026-08-05',
        ],
        'condition100' => [
            'description' => 'Hb 100-129 g/L AND Serum Iron <9 umol/L AND MCV <80 fL AND MCH <27 pg AND MCHC <320 g/L AND RDW >14.5% AND PCV/HCT <0.40 L/L AND Male AND RCC <4.3 x10^12/L',
            'risk_tier' => 3,
            'criteria_count' => 9,
            'active_from' => '2026-08-05',
        ],
    ];

    /**
     * Run the database seeds.
     *
     * Ids are not hardcoded: each condition is matched to an existing row by its
     * evaluator name (the unique dispatch key used by ConditionEvaluatorService).
     * If a matching row exists it is updated in place; otherwise a new id is
     * allocated automatically as max(existing id) + 1, so this seeder never
     * collides with ids owned by other clinical condition seeders.
     */
    public function run(): void
    {
        Log::info('SerumClinicalSeeder: Starting seeding');

        try {
            DB::beginTransaction();

            $nextId = (int) (ClinicalCondition::max('id') ?? 0);
            $createdCount = 0;
            $updatedCount = 0;

            foreach (self::CONDITIONS as $evaluator => $data) {
                $condition = ClinicalCondition::where('evaluator', $evaluator)->first();

                if ($condition) {
                    $condition->update([
                        'description' => $data['description'],
                        'risk_tier' => $data['risk_tier'],
                        'criteria_count' => $data['criteria_count'],
                        'is_active' => true,
                        'active_from' => $data['active_from'],
                    ]);
                    $updatedCount++;

                    continue;
                }

                $nextId++;

                ClinicalCondition::create([
                    'id' => $nextId,
                    'description' => $data['description'],
                    'evaluator' => $evaluator,
                    'risk_tier' => $data['risk_tier'],
                    'criteria_count' => $data['criteria_count'],
                    'is_active' => true,
                    'active_from' => $data['active_from'],
                ]);
                $createdCount++;
            }

            DB::commit();

            ClinicalCondition::clearCache();

            Log::info('SerumClinicalSeeder: Seeding completed', [
                'created' => $createdCount,
                'updated' => $updatedCount,
            ]);
        } catch (QueryException $e) {
            DB::rollBack();

            Log::error('SerumClinicalSeeder: Seeding failed', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
