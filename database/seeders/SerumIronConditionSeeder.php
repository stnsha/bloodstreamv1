<?php

namespace Database\Seeders;

use App\Models\ClinicalCondition;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\QueryException;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SerumIronConditionSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Condition definitions, keyed by evaluator method name (App\Services\ConditionEvaluatorService).
     * No 'id' here on purpose - ids are assigned automatically in run() to avoid colliding with
     * ids already owned by other clinical condition seeders.
     */
    private const CONDITIONS = [
        'condition101' => [
            'description' => 'Serum Iron <9 umol/L',
            'risk_tier' => 2,
            'criteria_count' => 1,
            'active_from' => '2026-06-02',
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
        Log::info('SerumIronConditionSeeder: Starting seeding');

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

            Log::info('SerumIronConditionSeeder: Seeding completed', [
                'created' => $createdCount,
                'updated' => $updatedCount,
            ]);
        } catch (QueryException $e) {
            DB::rollBack();

            Log::error('SerumIronConditionSeeder: Seeding failed', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
