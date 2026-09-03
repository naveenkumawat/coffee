<?php

return [
    'cache' => [
        'menu_ttl' => env('COFFEE_MENU_CACHE_TTL', 15),
    ],
    'company' => [
        'name' => env('APP_NAME', 'The88Coffees'),
        'support_email' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
    ],
    'pwa' => [
        // Prefer CUSTOMER_APP_URL; COFFEE_PWA_URL remains supported for existing deploys.
        'url' => env('CUSTOMER_APP_URL', env('COFFEE_PWA_URL', env('APP_URL', 'http://localhost'))),
    ],
    'checkout' => [
        'session_token_key' => 'customer_checkout.token',
        'api_token_cache_prefix' => 'customer_api_checkout_token:',
    ],
    'media' => [
        'disk' => env('COFFEE_MEDIA_DISK', 'public'),
        /** Soft target ~50–150KB; hard cap for uploads (kilobytes). */
        'max_kilobytes' => (int) env('COFFEE_MEDIA_MAX_KB', 512),
    ],
    'payments' => [
        'display_name' => env('COFFEE_PAYMENT_DISPLAY_NAME', env('APP_NAME', 'Coffee')),
        'instructions' => env(
            'COFFEE_PAYMENT_INSTRUCTIONS',
            'Please complete the payment and share your payment screenshot with the order number on WhatsApp.',
        ),
        'upi_id' => env('COFFEE_UPI_ID'),
        'phone' => env('COFFEE_PAYMENT_PHONE'),
        'qr_image_path' => env('COFFEE_PAYMENT_QR_IMAGE_PATH'),
        'whatsapp_number' => env('COFFEE_WHATSAPP_NUMBER'),
        'proof_max_kilobytes' => (int) env('COFFEE_PAYMENT_PROOF_MAX_KB', 5120),
    ],
    'fulfilment' => [
        'delivery_disclaimer' => env(
            'COFFEE_DELIVERY_DISCLAIMER',
            'Delivery will be arranged through a third-party service. Delivery charges are payable separately by the customer.',
        ),
    ],
    'timezone' => env('COFFEE_TIMEZONE', 'Asia/Kolkata'),

    /*
    |--------------------------------------------------------------------------
    | Realtime (R1) — Laravel Reverb
    |--------------------------------------------------------------------------
    |
    | Clients may connect only when enabled and credentials are present.
    | When disabled or misconfigured, REST/API continues normally.
    |
    */
    'realtime' => [
        'enabled' => (bool) env('COFFEE_REALTIME_ENABLED', env('BROADCAST_CONNECTION') === 'reverb'),
        'key' => env('REVERB_APP_KEY'),
        'host' => env('REVERB_HOST', 'localhost'),
        'port' => (int) env('REVERB_PORT', 8080),
        'scheme' => env('REVERB_SCHEME', 'http'),
    ],
];
