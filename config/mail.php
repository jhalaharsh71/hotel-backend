<?php

return [

    'default' => env('MAIL_MAILER', 'brevo'),

    'mailers' => [

        'brevo' => [
            'transport' => 'brevo',
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],
    ],

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'no-reply@example.com'),
        'name' => env('MAIL_FROM_NAME', 'Hotel Demo App'),
    ],

];
