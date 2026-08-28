<?php

namespace Database\Seeders;

use App\Models\ClinicalCondition;
use Exception;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClinicalConditionTypeSeeder extends Seeder
{
    /**
     * Snapshot of clinical_conditions.type as of 2026-08-13 (dev DB), grouped by value.
     * Used to replicate the current type assignments onto staging/production.
     */
    private const CC_IDS = [
        1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21,
        23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 35, 36, 37, 39, 41, 45,
        47, 48, 50, 51, 52, 53, 54, 55, 56, 57, 58, 59, 60, 61, 62, 63,
        76, 77, 78, 79, 82, 83, 84, 85, 86, 87, 88, 89, 90, 91, 92, 93, 94,
        95, 96, 97, 98, 99, 100, 101,
    ];

    private const CC_AO_IDS = [
        11, 22, 34, 38, 40, 42, 43, 44, 46, 49, 64, 65, 66, 67, 68, 69, 70,
        71, 72, 73, 74, 75, 80, 81,
    ];

    private const AO_IDS = [
        102, 103, 104, 105, 106, 107, 108, 109, 110, 111, 112, 113, 114, 115, 116,
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Log::info('ClinicalConditionTypeSeeder: Starting seeding');

        try {
            DB::beginTransaction();

            $updatedCc = ClinicalCondition::whereIn('id', self::CC_IDS)->update(['type' => 'CC']);
            $updatedCcAo = ClinicalCondition::whereIn('id', self::CC_AO_IDS)->update(['type' => 'CC + AO']);
            $updatedAo = ClinicalCondition::whereIn('id', self::AO_IDS)->update(['type' => 'AO']);

            DB::commit();

            ClinicalCondition::clearCache();

            Log::info('ClinicalConditionTypeSeeder: Seeding completed', [
                'cc_updated' => $updatedCc,
                'cc_ao_updated' => $updatedCcAo,
                'ao_updated' => $updatedAo,
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('ClinicalConditionTypeSeeder: Seeding failed', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
