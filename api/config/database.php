<?php

return [
    'default' => env('DB_CONNECTION', 'pgsql'),
    'connections' => [
        'pgsql' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', 'postgres'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'auth_db'),
            'username' => env('DB_USERNAME', 'auth_user'),
            'password' => env('DB_PASSWORD', 'secret'),
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
        ],
    ],
    'migrations' => 'migrations',
    'redis' => [
        'client' => env('REDIS_CLIENT', 'phpredis'),
        'default' => [
            'host' => env('REDIS_HOST', 'redis'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => 0,
        ],
        'cache' => [
            'host' => env('REDIS_HOST', 'redis'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => 1,
        ],
    ],
];
