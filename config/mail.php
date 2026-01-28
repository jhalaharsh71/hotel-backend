<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | We use Brevo API instead of SMTP.
    | SMTP often times out on Railway.
    |
    */

    'default' => env('MAIL_MAILER', 'brevo'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    */

    'mailers' => [

        /*
        |--------------------------------------------------
        | Brevo API Mailer (RECOMMENDED)
        |--------------------------------------------------
        */
        'brevo' => [
            'transport' => 'brevo',
        ],

        /*
        |--------------------------------------------------
        | SMTP (kept only as fallback, not used)
        |--------------------------------------------------
        */
        'smtp' => [
            'transport' => 'smtp',
            'host' => env('MAIL_HOST', 'smtp-relay.brevo.com'),
            'port' => env('MAIL_PORT', 587),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => 10,
        ],

        /*
        |--------------------------------------------------
        | Log mailer
        |--------------------------------------------------
        */
        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        /*
        |--------------------------------------------------
        | Array mailer
        |--------------------------------------------------
        */
        'array' => [
            'transport' => 'array',
        ],

        /*
        |--------------------------------------------------
        | Failover
        |--------------------------------------------------
        */
        'failover' => [
            'transport' => 'failover',
            'mailers' => ['brevo', 'log'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'jhalaharsh71@gmail.com'),
        'name' => env('MAIL_FROM_NAME', 'Hotel Demo App'),
    ],

];
