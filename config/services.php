<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Business Platform (Cloud API)
    |--------------------------------------------------------------------------
    |
    | Infrastructure credentials for transactional WhatsApp notifications.
    | Keep café public WhatsApp contact in Website Settings — do not reuse
    | these Meta Cloud API values as the customer-facing café number.
    |
    | Template names must match Meta-approved templates for your WABA.
    | Leave template env vars empty until approved names are known.
    |
    */
    'whatsapp' => [
        'enabled' => (bool) env('WHATSAPP_NOTIFICATIONS_ENABLED', false),
        'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
        'graph_base_url' => env('WHATSAPP_GRAPH_BASE_URL', 'https://graph.facebook.com'),
        'timeout' => (int) env('WHATSAPP_HTTP_TIMEOUT', 10),
        'connect_timeout' => (int) env('WHATSAPP_HTTP_CONNECT_TIMEOUT', 3),
        'language' => env('WHATSAPP_TEMPLATE_LANGUAGE', 'en'),
        'send_preparing' => (bool) env('WHATSAPP_SEND_PREPARING', false),
        'templates' => [
            'order_placed' => env('WHATSAPP_TEMPLATE_ORDER_PLACED'),
            'payment_proof_received' => env('WHATSAPP_TEMPLATE_PAYMENT_PROOF_RECEIVED'),
            'payment_confirmed' => env('WHATSAPP_TEMPLATE_PAYMENT_CONFIRMED'),
            'payment_proof_rejected' => env('WHATSAPP_TEMPLATE_PAYMENT_PROOF_REJECTED'),
            'order_accepted' => env('WHATSAPP_TEMPLATE_ORDER_ACCEPTED'),
            'order_preparing' => env('WHATSAPP_TEMPLATE_ORDER_PREPARING'),
            'order_ready_pickup' => env('WHATSAPP_TEMPLATE_ORDER_READY_PICKUP'),
            'order_ready_delivery' => env('WHATSAPP_TEMPLATE_ORDER_READY_DELIVERY'),
            'order_ready_dine_in' => env('WHATSAPP_TEMPLATE_ORDER_READY_DINE_IN'),
            'order_completed' => env('WHATSAPP_TEMPLATE_ORDER_COMPLETED'),
            'order_cancelled' => env('WHATSAPP_TEMPLATE_ORDER_CANCELLED'),
        ],
    ],

];
