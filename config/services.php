<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'consult_call' => [
        'api_url' => env('CONSULT_CALL_API_URL'),
        'username' => env('CONSULT_CALL_API_USERNAME'),
        'password' => env('CONSULT_CALL_API_PASSWORD'),
    ],

    'octopus' => [
        'api_url' => env('APP_ENV') === 'production'
            ? env('ODB_API_URL_PROD')
            : env('ODB_API_URL_LOCAL'),
    ],

];
