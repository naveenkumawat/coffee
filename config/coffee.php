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

    /*
    |--------------------------------------------------------------------------
    | Behaviour tracking (P2.1) — first-party personalisation foundation
    |--------------------------------------------------------------------------
    |
    | Optional interaction analytics for future recommendations/campaigns.
    | Disabling must never break browse/cart/checkout. Retention deletes only
    | raw behaviour events — never orders, payments, inventory, or audit rows.
    |
    */
    'behaviour' => [
        'enabled' => (bool) env('COFFEE_BEHAVIOUR_TRACKING_ENABLED', true),
        'retention_days' => (int) env('COFFEE_BEHAVIOUR_RETENTION_DAYS', 180),
        'metadata_max_bytes' => (int) env('COFFEE_BEHAVIOUR_METADATA_MAX_BYTES', 2048),
        'search_query_max_length' => (int) env('COFFEE_BEHAVIOUR_SEARCH_QUERY_MAX_LENGTH', 100),
        'visitor_ttl_days' => (int) env('COFFEE_BEHAVIOUR_VISITOR_TTL_DAYS', 180),
        'rate_limit_per_minute' => (int) env('COFFEE_BEHAVIOUR_RATE_LIMIT_PER_MINUTE', 60),
    ],
];
