<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Lab;
use App\Models\LabCredential;

class BMCLabSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $lab = Lab::firstOrCreate(
            ['code' => 'BMC'],
            [
                'name' => 'Borneo Medical Centre',
                'path' => 'BMC',
                'status' => 1,
            ]
        );

        $lab_id = $lab->id;

        LabCredential::firstOrCreate(
                [
                    'lab_id' => $lab_id,
                    'username' => config('credentials.lab.lab_4.username'),
                ],
                [
                    'user_id' => 1,
                    'password' => bcrypt(config('credentials.lab.lab_4.password')),
                    'role' => 'lab',
                    'is_active' => true,
                ]
            );
    }
}
