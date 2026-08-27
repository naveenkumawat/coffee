<?php

return [
    'cache' => [
        'menu_ttl' => env('COFFEE_MENU_CACHE_TTL', 15),
    ],
    'company' => [
        'name' => env('APP_NAME', 'Coffee'),
        'support_email' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
    ],
];
