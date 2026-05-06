<?php

namespace Database\Seeders;

use App\Models\Lab;
use App\Models\LabCredential;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class NexusCredentialSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $lab = Lab::firstOrCreate(
            ['code' => 'NEX'],
            [
                'name' => 'NEXUS',
                'path' => 'NEX',
                'status' => 1,
            ]
        );

        $lab_id = $lab->id;

        LabCredential::firstOrCreate(
                [
                    'lab_id' => $lab_id,
                    'username' => config('credentials.lab.lab_5.username'),
                ],
                [
                    'user_id' => 1,
                    'password' => bcrypt(config('credentials.lab.lab_5.password')),
                    'role' => 'lab',
                    'is_active' => true,
                ]
            );
    }
}
