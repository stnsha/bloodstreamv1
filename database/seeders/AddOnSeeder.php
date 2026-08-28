<?php

namespace Database\Seeders;

use App\Models\AddOn;
use Exception;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AddOnSeeder extends Seeder
{
    private const ADD_ONS = [
        'Anemia Profile',
        'Hepatitis A (HAG) + Hepatitis B Serology (HB3)',
        'ALEMO Package (Vitamin D + Vitamin B12 + Magnesium)',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Log::info('AddOnSeeder: Starting seeding');

        try {
            DB::beginTransaction();

            $seededCount = 0;

            foreach (self::ADD_ONS as $name) {
                AddOn::updateOrCreate(['name' => $name]);
                $seededCount++;
            }

            DB::commit();

            Log::info('AddOnSeeder: Seeding completed', [
                'total_seeded' => $seededCount,
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('AddOnSeeder: Seeding failed', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
