<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Loyalty & Rewards (P3.1 + P3.2)
    |--------------------------------------------------------------------------
    |
    | Production defaults are safe/off until the café chooses real economics.
    |
    | Eligible earning amount (V1):
    | merchandise after discounts (canonical taxable_amount / subtotal−discount),
    | excluding delivery_fee_amount and tax_amount (pre-tax earning).
    |
    | Stacking (V1):
    | catalog prices → promotions/referral coupon → loyalty reward → tax.
    |
    | Debt invariant (P3.2):
    | Ledger arithmetic is never silently clamped. Earn reversals may drive
    | available_points negative (loyalty debt). Redemption is blocked while
    | available_points < required cost. Future earnings reduce debt naturally.
    | Debt is never converted into money owed.
    |
    | Totals:
    | - available_points: running balance (may be negative = debt)
    | - lifetime_earned_points: sum of earn credits (not reduced by reversals)
    | - lifetime_redeemed_points: sum of redeem debits (not reduced by restores)
    | - lifetime_adjusted_points: net admin adjustments
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

    'redemption' => [
        'enabled' => (bool) env('COFFEE_LOYALTY_REDEMPTION_ENABLED', true),
        'allow_with_promotions' => (bool) env('COFFEE_LOYALTY_ALLOW_WITH_PROMOTIONS', true),
        'one_reward_per_order' => true,
    ],

    /*
    | Optional bridge: award loyalty ledger points from approved referral outcomes.
    | Default off — referral economics remain unchanged.
    */
    'referral_bridge' => [
        'enabled' => (bool) env('COFFEE_LOYALTY_REFERRAL_BRIDGE', false),
        'points' => (int) env('COFFEE_LOYALTY_REFERRAL_POINTS', 0),
    ],

    'customer_explanation' => env(
        'COFFEE_LOYALTY_EXPLANATION',
        'Earn loyalty points on paid completed orders based on your merchandise total after discounts. Delivery charges and tax do not earn points. Redeem points for available rewards at checkout.',
    ),

    'history_limit' => (int) env('COFFEE_LOYALTY_HISTORY_LIMIT', 20),
];
