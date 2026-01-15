<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            HL7LibrarySeeder::class,
            LabCredentialSeeder::class,
            InnoquestCodeMappingSeeder::class,
            TranslationSeeder::class,
            EurofinsLabSeeder::class,
            PanelInterpretationSeeder::class,
        ]);
    }
}