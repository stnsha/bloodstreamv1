<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Seeder Credentials
    |--------------------------------------------------------------------------
    */
    'seeder' => [
        'admin_email' => env('CREDENTIALS_SEEDER_ADMIN_EMAIL', 'admin@gmail.com'),
        'admin_name' => env('CREDENTIALS_SEEDER_ADMIN_NAME', 'superadmin'),
        'admin_password' => env('CREDENTIALS_SEEDER_ADMIN_PASSWORD', 'password'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Lab Credentials
    |--------------------------------------------------------------------------
    */
    'lab' => [
        'lab_1' => [
            'username' => env('CREDENTIALS_LAB_1_USERNAME', 'username'),
            'password' => env('CREDENTIALS_LAB_1_PASSWORD', 'password'),
        ],
        'lab_2' => [
            'username' => env('CREDENTIALS_LAB_2_USERNAME', 'username'),
            'password' => env('CREDENTIALS_LAB_2_PASSWORD', 'password'),
        ],
        'lab_3' => [
            'username' => env('CREDENTIALS_LAB_3_USERNAME', 'username'),
            'password' => env('CREDENTIALS_LAB_3_PASSWORD', 'password'),
        ],
    ],
];
