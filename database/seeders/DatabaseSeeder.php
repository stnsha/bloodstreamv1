<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Models\Lab;
use App\Models\LabCredential;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            HL7LibrarySeeder::class
        ]);

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

        LabCredential::create(
            [
                'user_id' => $user->id,
                'lab_id' => 1,
                'username' => 'DUM1ANA',
                'password' => bcrypt('fzElFOAz1RU1g8a'),
                'role' => 'lab',
                'is_active' => true,
            ]
        );

        LabCredential::create(
            [
                'user_id' => $user->id,
                'lab_id' => 3,
                'username' => 'NAV3CHO',
                'password' => bcrypt('mT5cV6bN4gH8sD1e'),
                'role' => 'lab',
                'is_active' => true,
            ]
        );

        LabCredential::create(
            [
                'user_id' => $user->id,
                'lab_id' => 2,
                'username' => 'INN3SAN',
                'password' => bcrypt('jP8xK2qL7fR9tZ3v'),
                'role' => 'lab',
                'is_active' => true,
            ]
        );
    }
}
