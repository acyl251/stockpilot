<?php

return [

    'default' => getenv('MAIL_MAILER') ?: env('MAIL_MAILER', 'smtp'),

    'mailers' => [

        'smtp' => [
            'transport'  => 'smtp',
            'scheme'     => null,
            'host'       => getenv('MAIL_HOST')     ?: env('MAIL_HOST',     'sandbox.smtp.mailtrap.io'),
            'port'       => (int) (getenv('MAIL_PORT') ?: env('MAIL_PORT',  2525)),
            'username'   => getenv('MAIL_USERNAME') ?: env('MAIL_USERNAME', '6fcc80dcfbfe61'),
            'password'   => getenv('MAIL_PASSWORD') ?: env('MAIL_PASSWORD', 'e18e582cc143a0'),
            'encryption' => getenv('MAIL_ENCRYPTION') ?: env('MAIL_ENCRYPTION', 'tls'),
            'timeout'    => null,
        ],

        'log' => [
            'transport' => 'log',
            'channel'   => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

    ],

    'from' => [
        'address' => getenv('MAIL_FROM_ADDRESS') ?: env('MAIL_FROM_ADDRESS', 'noreply@stockpilot.tn'),
        'name'    => getenv('MAIL_FROM_NAME')    ?: env('MAIL_FROM_NAME',    'StockPilot'),
    ],

];
