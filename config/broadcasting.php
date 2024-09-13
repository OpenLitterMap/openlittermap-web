<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    |
    | This option controls the default broadcaster that will be used by the
    | framework when an event needs to be broadcast. You may set this to
    | any of the connections defined in the "connections" array below.
    |
    | Supported: "pusher", "redis", "log", "null"
    |
    */

    'default' => env('BROADCAST_DRIVER', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Broadcast Connections
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the broadcast connections that will be used
    | to broadcast events to other systems or over websockets. Samples of
    | each available type of connection are provided inside this array.
    |
    */

    'connections' => [

        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            'options' => [
                'host' => env('REVERB_HOST', '127.0.0.1'),
                'port' => env('REVERB_PORT', 6002),
                'scheme' => env('REVERB_SCHEME', 'http'),
                'path' => env('REVERB_PATH', '/reverb'),
                'verify' => false,
            ],
        ],

//        'pusher' => [
//            'driver' => 'pusher',
//            'key' => env('PUSHER_APP_KEY'),
//            'secret' => env('PUSHER_APP_SECRET'),
//            'app_id' => env('PUSHER_APP_ID'),
//            'options' => [
//                'cluster' => env('PUSHER_APP_CLUSTER'),
//                'useTLS' => false,
//                'encrypted'  => false, // was commented out
//                'host'       => env('WEBSOCKET_BROADCAST_HOST'),
//                'port'       => 8080,
//                'scheme'     => 'http'
//            ],
//        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
