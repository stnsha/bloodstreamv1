<?php

namespace Database\Seeders;

use App\Models\Lab;
use App\Models\LabCredential;
use App\Models\User;
use Illuminate\Database\Seeder;

class LabCredentialSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::firstOrCreate(
            [
                'email' => 'anasuharosli.alphac@gmail.com',
            ],
            [
                'name' => 'superadmin',
                'password' => bcrypt('fzElFOAz1RU1g8a')
            ]
        );

        $labs = [
            [
                'name' => 'Dummy Lab Sdn Bhd',
            ],
            [
                'name' => 'Innoquest Pathology Sdn Bhd',
            ],
            [
                'name' => 'Navipath Diagnostics Sdn Bhd',
            ],
            [
                'name' => 'Premier Integrated Labs Sdn Bhd',
            ],
        ];

        foreach ($labs as $lab) {
            Lab::firstOrCreate(
                [
                    'name' => $lab['name'],
                ],
                [
                    'path' => generate_lab_path($lab['name']),
                    'code' => generate_lab_code($lab['name']),
                    'status' => 1,
                ]
            );
        }

        $labCredentials = [
            [
                'lab_id' => 1,
                'username' => 'DUM1ANA',
                'password' => 'fzElFOAz1RU1g8a',
            ],
            [
                'lab_id' => 3,
                'username' => 'NAV3CHO',
                'password' => 'mT5cV6bN4gH8sD1e',
            ],
            [
                'lab_id' => 2,
                'username' => 'INN2SAN',
                'password' => 'jP8xK2qL7fR9tZ3v',
            ],
        ];

        foreach ($labCredentials as $credential) {
            LabCredential::create([
                'user_id' => $user->id,
                'lab_id' => $credential['lab_id'],
                'username' => $credential['username'],
                'password' => bcrypt($credential['password']),
                'role' => 'lab',
                'is_active' => true,
            ]);
        }
    }
}
