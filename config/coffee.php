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

        /*
        | P2.2 derived personalisation profiles (rebuildable; not commercial truth).
        | Purchase signals use canonical completed orders — never client order_completed.
        */
        'profile' => [
            'version' => 1,
            'min_evidence_signals' => (int) env('COFFEE_PERSONALISATION_MIN_EVIDENCE', 3),
            'top_n' => (int) env('COFFEE_PERSONALISATION_TOP_N', 10),
            'recent_n' => (int) env('COFFEE_PERSONALISATION_RECENT_N', 8),
            'stale_after_hours' => (int) env('COFFEE_PERSONALISATION_STALE_HOURS', 24),
            'lookback_days' => (int) env('COFFEE_PERSONALISATION_LOOKBACK_DAYS', 180),
            'recency_half_life_days' => (float) env('COFFEE_PERSONALISATION_HALF_LIFE_DAYS', 30),
            'max_repeats_per_signal' => (int) env('COFFEE_PERSONALISATION_MAX_REPEATS', 5),
            'min_orders_for_spend_band' => (int) env('COFFEE_PERSONALISATION_MIN_ORDERS_SPEND', 2),
            'min_orders_for_frequency' => (int) env('COFFEE_PERSONALISATION_MIN_ORDERS_FREQ', 2),
            'weights' => [
                'purchase_item' => 10.0,
                'favourite_added' => 5.0,
                'cart_item_added' => 3.0,
                'product_customized' => 2.5,
                'product_viewed' => 1.0,
                'category_viewed' => 1.0,
                'favourite_removed' => -1.5,
                'cart_item_removed' => -0.5,
            ],
            'spend_bands' => [
                ['key' => 'low', 'max' => 200.0],
                ['key' => 'mid', 'max' => 500.0],
                ['key' => 'high', 'max' => null],
            ],
            'time_of_day' => [
                'morning' => [5, 11],
                'afternoon' => [11, 17],
                'evening' => [17, 21],
                'night' => [21, 5],
            ],
        ],

        /*
        | P2.3 hybrid recommendation engine (deterministic; no ML).
        */
        'recommendations' => [
            'default_limit' => (int) env('COFFEE_RECOMMENDATIONS_LIMIT', 8),
            'max_limit' => (int) env('COFFEE_RECOMMENDATIONS_MAX_LIMIT', 16),
            'cache_ttl_seconds' => (int) env('COFFEE_RECOMMENDATIONS_CACHE_TTL', 300),
            'trending_lookback_days' => (int) env('COFFEE_RECOMMENDATIONS_TRENDING_DAYS', 14),
            'popular_lookback_days' => (int) env('COFFEE_RECOMMENDATIONS_POPULAR_DAYS', 90),
            'new_arrival_days' => (int) env('COFFEE_RECOMMENDATIONS_NEW_DAYS', 30),
            'fbt_lookback_days' => (int) env('COFFEE_RECOMMENDATIONS_FBT_DAYS', 180),
            'fbt_min_orders' => (int) env('COFFEE_RECOMMENDATIONS_FBT_MIN_ORDERS', 3),
            'trending_min_actors' => (int) env('COFFEE_RECOMMENDATIONS_TRENDING_MIN_ACTORS', 2),
            'popular_min_orders' => (int) env('COFFEE_RECOMMENDATIONS_POPULAR_MIN_ORDERS', 2),
            'max_per_category' => (int) env('COFFEE_RECOMMENDATIONS_MAX_PER_CATEGORY', 3),
            'strategy_weights' => [
                'buy_again' => 12.0,
                'favourite' => 10.0,
                'repeated_interest' => 8.0,
                'affinity' => 7.0,
                'similar' => 5.0,
                'frequently_bought_together' => 6.5,
                'cart_context' => 6.0,
                'trending' => 4.5,
                'popular' => 4.0,
                'bestseller' => 3.5,
                'new_arrival' => 3.0,
                'featured' => 3.0,
            ],
            'strategy_priorities' => [
                'buy_again' => 100,
                'favourite' => 95,
                'repeated_interest' => 85,
                'frequently_bought_together' => 80,
                'cart_context' => 78,
                'affinity' => 70,
                'similar' => 55,
                'trending' => 45,
                'popular' => 40,
                'bestseller' => 35,
                'new_arrival' => 30,
                'featured' => 30,
            ],
            'cold_start_strategies' => [
                'trending',
                'popular',
                'bestseller',
                'new_arrival',
                'featured',
                'similar',
                'cart_context',
            ],
            'warm_strategies' => [
                'buy_again',
                'favourite',
                'repeated_interest',
                'affinity',
                'similar',
                'frequently_bought_together',
                'cart_context',
                'trending',
                'popular',
                'bestseller',
                'new_arrival',
                'featured',
            ],
            'context_strategies' => [
                'home' => null, // use warm/cold defaults
                'product_detail' => ['similar', 'frequently_bought_together', 'affinity', 'trending', 'popular', 'featured', 'new_arrival', 'bestseller'],
                'menu' => ['affinity', 'trending', 'popular', 'new_arrival', 'featured', 'bestseller', 'repeated_interest'],
                'cart' => ['cart_context', 'frequently_bought_together', 'buy_again', 'favourite', 'affinity', 'trending', 'popular'],
                'post_order' => ['buy_again', 'frequently_bought_together', 'affinity', 'trending', 'popular', 'featured'],
            ],
        ],

        /*
        | P2.4 campaign / popup engine.
        */
        'campaigns' => [
            'cache_ttl_seconds' => (int) env('COFFEE_CAMPAIGNS_CACHE_TTL', 120),
            'popup_max_per_page' => 1,
        ],
    ],
];
