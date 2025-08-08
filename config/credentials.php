<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Seeder Credentials
    |--------------------------------------------------------------------------
    */
    'seeder' => [
        'admin_email' => env('CREDENTIALS_SEEDER_ADMIN_EMAIL', 'anasuharosli.alphac@gmail.com'),
        'admin_name' => env('CREDENTIALS_SEEDER_ADMIN_NAME', 'superadmin'),
        'admin_password' => env('CREDENTIALS_SEEDER_ADMIN_PASSWORD', 'fzElFOAz1RU1g8a'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Lab Credentials
    |--------------------------------------------------------------------------
    */
    'lab' => [
        'lab_1' => [
            'username' => env('CREDENTIALS_LAB_1_USERNAME', 'DUM1ANA'),
            'password' => env('CREDENTIALS_LAB_1_PASSWORD', 'fzElFOAz1RU1g8a'),
        ],
        'lab_2' => [
            'username' => env('CREDENTIALS_LAB_2_USERNAME', 'INN2SAN'),
            'password' => env('CREDENTIALS_LAB_2_PASSWORD', 'jP8xK2qL7fR9tZ3v'),
        ],
        'lab_3' => [
            'username' => env('CREDENTIALS_LAB_3_USERNAME', 'NAV3CHO'),
            'password' => env('CREDENTIALS_LAB_3_PASSWORD', 'mT5cV6bN4gH8sD1e'),
        ],
    ],
];