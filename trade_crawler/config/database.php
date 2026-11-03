<?php
return [
    'default' => 'mongodb',
    'connections' => [
        'mongodb' => [
            'driver' => 'mongodb',
            'dsn' => env('DB_DSN', 'mongodb://mongodb:27017'),
            'database' => env('DB_DATABASE', 'my_trade'),
        ],
    ],
];
