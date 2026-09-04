<?php

namespace App\Support\Targeting;

use App\Services\Targeting\TargetingRuleValidator;

/**
 * Operator-friendly targeting templates generated from validator-supported schemas.
 */
class TargetingRuleTemplates
{
    public function __construct(
        protected TargetingRuleValidator $validator,
    ) {}

    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     meaning: string,
     *     when_to_use: string,
     *     scope: list<string>,
     *     rules: array{all: list<array<string, mixed>>, any: list<array<string, mixed>>, exclude: list<array<string, mixed>>}
     * }>
     */
    public function all(): array
    {
        $templates = [
            [
                'key' => 'everyone',
                'label' => 'Everyone',
                'meaning' => 'Matches guests and logged-in customers.',
                'when_to_use' => 'Broad campaigns or homepage sections with no audience filter.',
                'scope' => ['segment', 'campaign', 'home_section'],
                'rules' => $this->groups([
                    ['type' => 'identity', 'op' => 'eq', 'value' => 'everyone'],
                ]),
            ],
            [
                'key' => 'guests',
                'label' => 'Guests only',
                'meaning' => 'People browsing without an account.',
                'when_to_use' => 'Signup prompts and guest-only merchandising.',
                'scope' => ['segment', 'campaign', 'home_section'],
                'rules' => $this->groups([
                    ['type' => 'identity', 'op' => 'eq', 'value' => 'guest'],
                ]),
            ],
            [
                'key' => 'authenticated',
                'label' => 'Logged-in customers',
                'meaning' => 'Customers who are signed in.',
                'when_to_use' => 'Loyalty, account, or member-only messaging.',
                'scope' => ['segment', 'campaign', 'home_section'],
                'rules' => $this->groups([
                    ['type' => 'identity', 'op' => 'eq', 'value' => 'authenticated'],
                ]),
            ],
            [
                'key' => 'first_order',
                'label' => 'First-order customers',
                'meaning' => 'Customers who have not completed an order yet (or first-order signal is true).',
                'when_to_use' => 'Welcome offers and first-purchase nudges.',
                'scope' => ['segment', 'campaign', 'home_section'],
                'rules' => $this->groups([
                    ['type' => 'first_order', 'op' => 'eq', 'value' => true],
                ]),
            ],
            [
                'key' => 'returning_buyers',
                'label' => 'Returning buyers',
                'meaning' => 'Customers marked as returning buyers.',
                'when_to_use' => 'Retention and repeat-purchase campaigns.',
                'scope' => ['segment', 'campaign', 'home_section'],
                'rules' => $this->groups([
                    ['type' => 'returning_buyer', 'op' => 'eq', 'value' => true],
                ]),
            ],
            [
                'key' => 'high_value',
                'label' => 'High-value customers',
                'meaning' => 'Spend band is high (from personalisation profiles).',
                'when_to_use' => 'Premium offers and VIP messaging.',
                'scope' => ['segment', 'campaign', 'home_section'],
                'rules' => $this->groups([
                    ['type' => 'spend_band', 'op' => 'eq', 'value' => 'high'],
                ]),
            ],
            [
                'key' => 'has_favourites',
                'label' => 'Customers with favourites',
                'meaning' => 'Has at least one favourite product.',
                'when_to_use' => 'Favourite-based recommendations and reminders.',
                'scope' => ['segment', 'campaign', 'home_section'],
                'rules' => $this->groups([
                    ['type' => 'has_favourites', 'op' => 'eq', 'value' => true],
                ]),
            ],
            [
                'key' => 'loyalty_high',
                'label' => 'High loyalty points',
                'meaning' => 'Loyalty points band is high.',
                'when_to_use' => 'Reward redemption reminders for rich balances.',
                'scope' => ['segment', 'campaign', 'home_section'],
                'rules' => $this->groups([
                    ['type' => 'loyalty_points_band', 'op' => 'eq', 'value' => 'high'],
                ]),
            ],
            [
                'key' => 'near_reward',
                'label' => 'Near loyalty reward',
                'meaning' => 'Customer is close to affording a reward.',
                'when_to_use' => 'Nudge customers to place one more order.',
                'scope' => ['segment', 'campaign', 'home_section'],
                'rules' => $this->groups([
                    ['type' => 'loyalty_near_reward', 'op' => 'eq', 'value' => true],
                ]),
            ],
            [
                'key' => 'loyalty_debt',
                'label' => 'Loyalty debt customers',
                'meaning' => 'Available loyalty points are negative (debt).',
                'when_to_use' => 'Ops follow-up; usually exclude from redemption promos.',
                'scope' => ['segment', 'campaign', 'home_section'],
                'rules' => $this->groups([
                    ['type' => 'loyalty_debt', 'op' => 'eq', 'value' => true],
                ]),
            ],
            [
                'key' => 'recently_redeemed',
                'label' => 'Recently redeemed',
                'meaning' => 'Redeemed a loyalty reward recently.',
                'when_to_use' => 'Thank-you or “earn again” campaigns.',
                'scope' => ['segment', 'campaign', 'home_section'],
                'rules' => $this->groups([
                    ['type' => 'loyalty_recent_redeemer', 'op' => 'eq', 'value' => true],
                ]),
            ],
            [
                'key' => 'lapsed',
                'label' => 'Lapsed customers',
                'meaning' => 'Last purchase was more than 30 days ago.',
                'when_to_use' => 'Win-back offers.',
                'scope' => ['segment', 'campaign', 'home_section'],
                'rules' => $this->groups([
                    ['type' => 'last_purchase_days', 'op' => 'gte', 'value' => 30],
                ]),
            ],
            [
                'key' => 'coffee_and_high_value',
                'label' => 'Coffee lovers AND high value',
                'meaning' => 'All rules must match: hot-coffee affinity and high spend band.',
                'when_to_use' => 'Combined affinity + value targeting.',
                'scope' => ['segment', 'campaign', 'home_section'],
                'rules' => $this->groups([
                    ['type' => 'category_affinity', 'op' => 'eq', 'value' => 'hot-coffee'],
                    ['type' => 'spend_band', 'op' => 'eq', 'value' => 'high'],
                ]),
            ],
            [
                'key' => 'guests_or_first_order',
                'label' => 'Guests OR first-order customers',
                'meaning' => 'Any rule in the “any” group may match.',
                'when_to_use' => 'Acquisition messaging that covers guests and new accounts.',
                'scope' => ['segment', 'campaign', 'home_section'],
                'rules' => [
                    'all' => [],
                    'any' => [
                        ['type' => 'identity', 'op' => 'eq', 'value' => 'guest'],
                        ['type' => 'first_order', 'op' => 'eq', 'value' => true],
                    ],
                    'exclude' => [],
                ],
            ],
            [
                'key' => 'cart_has_product_context',
                'label' => 'Cart contains a product (campaign)',
                'meaning' => 'Uses campaign cart context. Replace product id with a real catalog id.',
                'when_to_use' => 'Cart upsells tied to a specific product already in cart.',
                'scope' => ['campaign'],
                'rules' => $this->groups([
                    ['type' => 'cart_contains_product', 'op' => 'eq', 'value' => 1],
                ]),
            ],
            [
                'key' => 'delivery_fulfilment',
                'label' => 'Delivery fulfilment (campaign)',
                'meaning' => 'Current checkout fulfilment is delivery.',
                'when_to_use' => 'Delivery-only banners or checkout prompts.',
                'scope' => ['campaign'],
                'rules' => $this->groups([
                    ['type' => 'fulfilment_method', 'op' => 'eq', 'value' => 'delivery'],
                ]),
            ],
        ];

        return array_values(array_filter(
            $templates,
            fn (array $template): bool => $this->rulesUseOnlyAllowedTypes($template['rules'], $template['scope']),
        ));
    }

    /**
     * @param  list<string>  $scopes
     * @return list<array<string, mixed>>
     */
    public function forScope(string $scope): array
    {
        return array_values(array_filter(
            $this->all(),
            fn (array $template): bool => in_array($scope, $template['scope'], true),
        ));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function keyedForScope(string $scope): array
    {
        $keyed = [];

        foreach ($this->forScope($scope) as $template) {
            $keyed[$template['key']] = $template;
        }

        return $keyed;
    }

    /**
     * @return array{all: list<array<string, mixed>>, any: list<array<string, mixed>>, exclude: list<array<string, mixed>>}
     */
    public function emptyRules(): array
    {
        return ['all' => [], 'any' => [], 'exclude' => []];
    }

    /**
     * Human-readable option reference derived from the validator.
     *
     * @return list<array{key: string, label: string, meaning: string, when_to_use: string, example: array<string, mixed>}>
     */
    public function optionReference(string $scope = 'segment'): array
    {
        $allowed = $scope === 'campaign'
            ? $this->validator->campaignRuleTypes()
            : $this->validator->segmentRuleTypes();

        $labels = $this->validator->ruleTypeLabels();
        $examples = $this->exampleValues();

        $items = [];

        foreach ($allowed as $type) {
            $exampleValue = $examples[$type] ?? true;
            $items[] = [
                'key' => $type,
                'label' => $labels[$type] ?? $type,
                'meaning' => $this->meaningFor($type),
                'when_to_use' => $this->whenToUseFor($type),
                'example' => [
                    'type' => $type,
                    'op' => $this->defaultOpFor($type),
                    'value' => $exampleValue,
                ],
            ];
        }

        return $items;
    }

    /**
     * @param  list<array{type: string, op: string, value: mixed}>  $all
     * @return array{all: list<array<string, mixed>>, any: list<array<string, mixed>>, exclude: list<array<string, mixed>>}
     */
    protected function groups(array $all): array
    {
        return [
            'all' => $all,
            'any' => [],
            'exclude' => [],
        ];
    }

    /**
     * @param  array{all: list<array<string, mixed>>, any: list<array<string, mixed>>, exclude: list<array<string, mixed>>}  $rules
     * @param  list<string>  $scopes
     */
    protected function rulesUseOnlyAllowedTypes(array $rules, array $scopes): bool
    {
        $campaignOnly = in_array('campaign', $scopes, true)
            && ! in_array('segment', $scopes, true)
            && ! in_array('home_section', $scopes, true);

        $allowed = $campaignOnly
            ? $this->validator->campaignRuleTypes()
            : $this->validator->segmentRuleTypes();

        foreach (['all', 'any', 'exclude'] as $group) {
            foreach ($rules[$group] as $rule) {
                if (! in_array($rule['type'], $allowed, true)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    protected function exampleValues(): array
    {
        return [
            'identity' => 'authenticated',
            'has_sufficient_evidence' => true,
            'category_affinity' => 'hot-coffee',
            'product_affinity' => 'cafe-latte',
            'flavour_affinity' => 'vanilla',
            'favourite_product' => 1,
            'has_favourites' => true,
            'favourite_count' => 2,
            'previous_purchase' => 1,
            'repeat_purchase' => 1,
            'purchased_category' => 'hot-coffee',
            'recent_product' => 1,
            'recent_category' => 'hot-coffee',
            'min_interactions' => 3,
            'spend_band' => 'high',
            'time_of_day' => 'morning',
            'completed_orders' => 3,
            'first_order' => true,
            'returning_buyer' => true,
            'last_purchase_days' => 30,
            'orders_per_30d' => 2,
            'days_since_activity' => 14,
            'returning_visitor' => true,
            'new_visitor' => true,
            'location_city' => 'Pune',
            'location_zone' => 'Koregaon Park',
            'location_available' => true,
            'loyalty_enabled' => true,
            'loyalty_points_gte' => 100,
            'loyalty_points_lte' => 499,
            'loyalty_points_band' => 'medium',
            'loyalty_reward_available' => true,
            'loyalty_reward_not_available' => true,
            'loyalty_near_reward' => true,
            'loyalty_recent_redeemer' => true,
            'loyalty_recent_earner' => true,
            'loyalty_debt' => true,
            'loyalty_redemption_blocked' => true,
            'current_product' => 1,
            'current_category' => 1,
            'cart_contains_product' => 1,
            'cart_contains_category' => 1,
            'fulfilment_method' => 'delivery',
            'segment_matches' => 1,
            'segment_not_matches' => 1,
        ];
    }

    protected function defaultOpFor(string $type): string
    {
        return match ($type) {
            'loyalty_points_gte', 'completed_orders', 'favourite_count', 'min_interactions',
            'orders_per_30d', 'last_purchase_days', 'days_since_activity' => 'gte',
            'loyalty_points_lte' => 'lte',
            default => 'eq',
        };
    }

    protected function meaningFor(string $type): string
    {
        return match ($type) {
            'identity' => 'Whether the visitor is a guest, logged-in customer, or everyone.',
            'spend_band' => 'Customer spend band from profile (low / mid / high).',
            'loyalty_points_band' => 'Loyalty points band (none / low / medium / high).',
            'loyalty_near_reward' => 'True when the customer is close to the next affordable reward.',
            'loyalty_debt' => 'True when available loyalty points are below zero.',
            'segment_matches' => 'Matches an active audience segment by id (campaign only).',
            'cart_contains_product' => 'Cart currently contains the given product id (campaign only).',
            'fulfilment_method' => 'Current fulfilment method such as takeaway, delivery, or dine_in.',
            default => ($this->validator->ruleTypeLabels()[$type] ?? $type).' targeting signal.',
        };
    }

    protected function whenToUseFor(string $type): string
    {
        return match ($type) {
            'identity' => 'Separate guest acquisition from member messaging.',
            'spend_band' => 'Target high or low spenders with different offers.',
            'loyalty_near_reward' => 'Encourage one more purchase before a reward unlocks.',
            'loyalty_debt' => 'Find accounts that need loyalty follow-up.',
            'segment_matches' => 'Reuse a saved audience segment inside a campaign.',
            'cart_contains_product' => 'Show a cart upsell when a product is already in the cart.',
            default => 'Use when this signal matches the audience you want to reach.',
        };
    }
}
