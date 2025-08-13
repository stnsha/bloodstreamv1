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
                'email' => config('credentials.seeder.admin_email'),
            ],
            [
                'name' => config('credentials.seeder.admin_name'),
                'password' => bcrypt(config('credentials.seeder.admin_password'))
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
                'username' => config('credentials.lab.lab_1.username'),
                'password' => config('credentials.lab.lab_1.password'),
                'role' => 'admin',
            ],
            [
                'lab_id' => 2,
                'username' => config('credentials.lab.lab_2.username'),
                'password' => config('credentials.lab.lab_2.password'),
                'role' => 'lab',
            ],
            [
                'lab_id' => 3,
                'username' => config('credentials.lab.lab_3.username'),
                'password' => config('credentials.lab.lab_3.password'),
                'role' => 'lab',
            ],
        ];

        foreach ($labCredentials as $credential) {
            LabCredential::create([
                'user_id' => $user->id,
                'lab_id' => $credential['lab_id'],
                'username' => $credential['username'],
                'password' => bcrypt($credential['password']),
                'role' => $credential['role'],
                'is_active' => true,
            ]);
        }
    }
}