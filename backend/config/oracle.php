<?php

return [
    'driver'         => 'oracle',
    'host'           => env('DB_HOST', 'oracle'),
    'port'           => env('DB_PORT', '1521'),
    'database'       => env('DB_DATABASE', 'XEPDB1'),
    'service_name'   => env('DB_DATABASE', 'XEPDB1'),
    'username'       => env('DB_USERNAME', 'stockpilot'),
    'password'       => env('DB_PASSWORD', ''),
    'charset'        => 'AL32UTF8',
    'prefix'         => '',
    'prefix_schema'  => '',
];
