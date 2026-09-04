<?php

return [

    /*
    |--------------------------------------------------------------------------
    | P3.1 Loyalty & Rewards Foundation
    |--------------------------------------------------------------------------
    |
    | Production defaults are safe/off until the café chooses real economics.
    | DemoSeeder may enable a clearly demo-only rate locally.
    |
    | Eligible amount policy (V1):
    | merchandise after discounts (canonical taxable_amount / subtotal−discount),
    | excluding delivery_fee_amount and tax_amount (pre-tax earning).
    |
    | Negative balance invariant (P3.2):
    | Ledger arithmetic is never silently clamped. Reversals that would drive
    | available_points below zero are rejected until redemption rules define
    | explicit debt handling.
    |
    */

    'enabled' => (bool) env('COFFEE_LOYALTY_ENABLED', false),

    /*
    | Only orders completed on/after this timestamp earn points.
    | Null/empty = no historical backfill boundary beyond "from now when enabled".
    */
    'effective_at' => env('COFFEE_LOYALTY_EFFECTIVE_AT'),

    'earning' => [
        'points_per_currency_unit' => (int) env('COFFEE_LOYALTY_POINTS_PER_UNIT', 1),
        'currency_unit' => (float) env('COFFEE_LOYALTY_CURRENCY_UNIT', 1),
        'minimum_eligible_amount' => (float) env('COFFEE_LOYALTY_MIN_ELIGIBLE', 0),
        'rounding' => 'floor',
        'eligible_amount_policy' => 'merchandise_after_discount_ex_tax_ex_delivery',
    ],

    'customer_explanation' => env(
        'COFFEE_LOYALTY_EXPLANATION',
        'Earn loyalty points on paid completed orders based on your merchandise total after discounts. Delivery charges and tax do not earn points. Redemption coming soon.',
    ),

    'history_limit' => (int) env('COFFEE_LOYALTY_HISTORY_LIMIT', 20),
];
