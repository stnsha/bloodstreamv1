<?php

namespace Database\Seeders;

use App\Models\ActionMaster;
use Illuminate\Database\QueryException;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Initial options for the consult call detail "Action" dropdown (action_master).
 *
 * Safe to re-run: existing names are left untouched, so an option an admin has
 * since renamed or deactivated is not recreated or reactivated.
 *
 * Run: php artisan db:seed --class=ActionMasterSeeder
 */
class ActionMasterSeeder extends Seeder
{
    private const ACTIONS = [
        'N10 ON',
        'N20 ON',
        'A10/20 ON',
        'GXR500 ON',
        'GXR1 ON',
        'N10 + GXR500 ON',
        'N10 + GXR1 ON',
        'A10/20 + GXR500 ON',
        'IF500 OD',
        'PI-EPA 2OD',
        'Lifestyle/supplement',
        'Already under follow up',
        'Already on medication',
        'Consulted at outlet',
        'Not keen to proceed',
        'Advised to visit GP',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Log::info('ActionMasterSeeder: Starting seeding');

        try {
            DB::beginTransaction();

            $created = 0;

            foreach (self::ACTIONS as $name) {
                $action = ActionMaster::firstOrCreate(['name' => $name], ['is_active' => true]);
                if ($action->wasRecentlyCreated) {
                    $created++;
                }
            }

            DB::commit();

            Log::info('ActionMasterSeeder: Seeding completed', [
                'total' => count(self::ACTIONS),
                'created' => $created,
            ]);
        } catch (QueryException $e) {
            DB::rollBack();

            Log::error('ActionMasterSeeder: Seeding failed', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
