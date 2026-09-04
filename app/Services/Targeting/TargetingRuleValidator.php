<?php

namespace App\Services\Targeting;

use Illuminate\Validation\ValidationException;

class TargetingRuleValidator
{
    /**
     * Base commerce/personalisation signals shared by segments and campaigns.
     *
     * @return list<string>
     */
    public function baseRuleTypes(): array
    {
        return [
            'identity',
            'has_sufficient_evidence',
            'category_affinity',
            'product_affinity',
            'flavour_affinity',
            'favourite_product',
            'has_favourites',
            'favourite_count',
            'previous_purchase',
            'repeat_purchase',
            'purchased_category',
            'recent_product',
            'recent_category',
            'min_interactions',
            'spend_band',
            'time_of_day',
            'completed_orders',
            'first_order',
            'returning_buyer',
            'last_purchase_days',
            'orders_per_30d',
            'days_since_activity',
            'returning_visitor',
            'new_visitor',
            'location_city',
            'location_zone',
            'location_available',
            'loyalty_enabled',
            'loyalty_points_gte',
            'loyalty_points_lte',
            'loyalty_points_band',
            'loyalty_reward_available',
            'loyalty_reward_not_available',
            'loyalty_near_reward',
            'loyalty_recent_redeemer',
            'loyalty_recent_earner',
            'loyalty_debt',
            'loyalty_redemption_blocked',
        ];
    }

    /**
     * Human-readable labels for Admin rule builders.
     *
     * @return array<string, string>
     */
    public function ruleTypeLabels(): array
    {
        return [
            'identity' => 'Identity',
            'has_sufficient_evidence' => 'Has sufficient evidence',
            'category_affinity' => 'Category affinity',
            'product_affinity' => 'Product affinity',
            'flavour_affinity' => 'Flavour affinity',
            'favourite_product' => 'Favourite product',
            'has_favourites' => 'Has favourites',
            'favourite_count' => 'Favourite count',
            'previous_purchase' => 'Previous purchase',
            'repeat_purchase' => 'Repeat purchase',
            'purchased_category' => 'Purchased category',
            'recent_product' => 'Recent product',
            'recent_category' => 'Recent category',
            'min_interactions' => 'Min interactions',
            'spend_band' => 'Spend band',
            'time_of_day' => 'Time of day',
            'completed_orders' => 'Completed orders',
            'first_order' => 'First order',
            'returning_buyer' => 'Returning buyer',
            'last_purchase_days' => 'Last purchase days',
            'orders_per_30d' => 'Orders per 30d',
            'days_since_activity' => 'Days since activity',
            'returning_visitor' => 'Returning visitor',
            'new_visitor' => 'New visitor',
            'location_city' => 'Location city',
            'location_zone' => 'Location zone',
            'location_available' => 'Location available',
            'loyalty_enabled' => 'Loyalty enabled',
            'loyalty_points_gte' => 'Points at least',
            'loyalty_points_lte' => 'Points at most',
            'loyalty_points_band' => 'Loyalty points band',
            'loyalty_reward_available' => 'Reward available',
            'loyalty_reward_not_available' => 'Reward not available',
            'loyalty_near_reward' => 'Near next reward',
            'loyalty_recent_redeemer' => 'Redeemed recently',
            'loyalty_recent_earner' => 'Earned recently',
            'loyalty_debt' => 'Loyalty debt',
            'loyalty_redemption_blocked' => 'Redemption blocked',
            'current_product' => 'Current product',
            'current_category' => 'Current category',
            'cart_contains_product' => 'Cart contains product',
            'cart_contains_category' => 'Cart contains category',
            'fulfilment_method' => 'Fulfilment method',
            'segment_matches' => 'Segment matches',
            'segment_not_matches' => 'Segment not matches',
        ];
    }

    /**
     * Page/cart context signals — campaign-only (not stored on segments).
     *
     * @return list<string>
     */
    public function campaignContextRuleTypes(): array
    {
        return [
            'current_product',
            'current_category',
            'cart_contains_product',
            'cart_contains_category',
            'fulfilment_method',
            'segment_matches',
            'segment_not_matches',
        ];
    }

    /**
     * @return list<string>
     */
    public function segmentRuleTypes(): array
    {
        return $this->baseRuleTypes();
    }

    /**
     * @return list<string>
     */
    public function campaignRuleTypes(): array
    {
        return array_values(array_unique(array_merge(
            $this->baseRuleTypes(),
            $this->campaignContextRuleTypes(),
        )));
    }

    /**
     * @return list<string>
     */
    public function allowedOperators(): array
    {
        return ['eq', 'neq', 'gte', 'lte', 'gt', 'lt', 'includes', 'excludes', 'in', 'not_in'];
    }

    /**
     * @param  array<string, mixed>  $rules
     * @param  list<string>  $allowedTypes
     * @return array{all: list<array<string, mixed>>, any: list<array<string, mixed>>, exclude: list<array<string, mixed>>}
     */
    public function validateRuleGroups(array $rules, array $allowedTypes, string $fieldPrefix = 'rules'): array
    {
        return [
            'all' => $this->normalizeRuleGroup($rules['all'] ?? [], $fieldPrefix.'.all', $allowedTypes),
            'any' => $this->normalizeRuleGroup($rules['any'] ?? [], $fieldPrefix.'.any', $allowedTypes),
            'exclude' => $this->normalizeRuleGroup($rules['exclude'] ?? [], $fieldPrefix.'.exclude', $allowedTypes),
        ];
    }

    /**
     * @param  list<string>  $allowedTypes
     * @return list<array{type: string, op: string, value: mixed}>
     */
    protected function normalizeRuleGroup(mixed $group, string $field, array $allowedTypes): array
    {
        if ($group === null || $group === []) {
            return [];
        }

        if (! is_array($group)) {
            throw ValidationException::withMessages([
                $field => 'Rule group must be an array.',
            ]);
        }

        $allowedOps = $this->allowedOperators();
        $normalized = [];

        foreach ($group as $index => $rule) {
            if (! is_array($rule)) {
                throw ValidationException::withMessages([
                    "{$field}.{$index}" => 'Each rule must be an object.',
                ]);
            }

            $type = (string) ($rule['type'] ?? '');
            $op = (string) ($rule['op'] ?? 'eq');

            if (! in_array($type, $allowedTypes, true)) {
                throw ValidationException::withMessages([
                    "{$field}.{$index}.type" => 'Unsupported rule type.',
                ]);
            }

            if (! in_array($op, $allowedOps, true)) {
                throw ValidationException::withMessages([
                    "{$field}.{$index}.op" => 'Unsupported operator.',
                ]);
            }

            if (! array_key_exists('value', $rule)) {
                throw ValidationException::withMessages([
                    "{$field}.{$index}.value" => 'Rule value is required.',
                ]);
            }

            if (in_array($type, ['segment_matches', 'segment_not_matches'], true) && (int) $rule['value'] <= 0) {
                throw ValidationException::withMessages([
                    "{$field}.{$index}.value" => 'A valid segment id is required.',
                ]);
            }

            $normalized[] = [
                'type' => $type,
                'op' => $op,
                'value' => $rule['value'],
            ];
        }

        return $normalized;
    }
}
