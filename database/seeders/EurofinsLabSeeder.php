<?php

namespace Database\Seeders;

use App\Models\Lab;
use Illuminate\Database\Seeder;

class EurofinsLabSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Lab::firstOrCreate(
            ['code' => 'EUR'],
            [
                'name' => 'Eurofins Malaysia',
                'path' => 'Eurofins',
                'status' => 1,
            ]
        );
    }
}
