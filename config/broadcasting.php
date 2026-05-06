<?php

use Illuminate\Support\Arr;

return [

    'default' => env('BROADCAST_DRIVER', 'log'),

    'connections' => [

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => [
                'host' => env('PUSHER_HOST', '127.0.0.1'),
                'port' => env('PUSHER_PORT', 6001),
                'scheme' => env('PUSHER_SCHEME', 'http'),
                'encrypted' => false,
                'useTLS' => false,
            ],
            'client_options' => [
                // Reconnection settings
                'max_retries' => 5,
                'retry_delay' => 1000,
            ],
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
        ],
    ],

    'middleware' => [
        'web',
        \Illuminate\Auth\Middleware\Authenticate::class,
    ],

];