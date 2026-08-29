<?php

return [
    'cache' => [
        'menu_ttl' => env('COFFEE_MENU_CACHE_TTL', 15),
    ],
    'company' => [
        'name' => env('APP_NAME', 'Coffee'),
        'support_email' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
    ],
    'checkout' => [
        'session_token_key' => 'customer_checkout.token',
    ],
    'payments' => [
        'display_name' => env('COFFEE_PAYMENT_DISPLAY_NAME', env('APP_NAME', 'Coffee')),
        'instructions' => env(
            'COFFEE_PAYMENT_INSTRUCTIONS',
            'Please complete the payment and share your payment screenshot with the order number on WhatsApp.',
        ),
        'upi_id' => env('COFFEE_UPI_ID'),
        'whatsapp_number' => env('COFFEE_WHATSAPP_NUMBER'),
    ],
];
