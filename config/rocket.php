<?php

return [
    'account' => [
        'username' => env('ROCKET_CHAT_USERNAME'),
        'password' => env('ROCKET_CHAT_PASSWORD'),
    ],
    'cache' => [
        'ttl' => env('ROCKET_CHAT_CACHE_TTL', 60),
    ]
];
