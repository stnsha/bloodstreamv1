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
                'email' => env('SEEDER_ADMIN_EMAIL', 'admin@example.com'),
            ],
            [
                'name' => env('SEEDER_ADMIN_NAME', 'superadmin'),
                'password' => bcrypt(env('SEEDER_ADMIN_PASSWORD', 'defaultpassword'))
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
                'username' => env('LAB_1_USERNAME', 'dummy_user1'),
                'password' => env('LAB_1_PASSWORD', 'dummy_pass1'),
            ],
            [
                'lab_id' => 3,
                'username' => env('LAB_3_USERNAME', 'dummy_user3'),
                'password' => env('LAB_3_PASSWORD', 'dummy_pass3'),
            ],
            [
                'lab_id' => 2,
                'username' => env('LAB_2_USERNAME', 'dummy_user2'),
                'password' => env('LAB_2_PASSWORD', 'dummy_pass2'),
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
