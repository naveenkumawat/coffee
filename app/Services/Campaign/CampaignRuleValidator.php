<?php

namespace App\Services\Campaign;

use App\Enums\CampaignCtaType;
use App\Enums\CampaignPlacement;
use App\Enums\CampaignTriggerType;
use Illuminate\Validation\ValidationException;

class CampaignRuleValidator
{
    /**
     * @return list<string>
     */
    public function allowedRuleTypes(): array
    {
        return [
            'identity',
            'has_sufficient_evidence',
            'category_affinity',
            'product_affinity',
            'flavour_affinity',
            'favourite_product',
            'previous_purchase',
            'repeat_purchase',
            'recent_product',
            'recent_category',
            'min_interactions',
            'spend_band',
            'time_of_day',
            'completed_orders',
            'first_order',
            'returning_buyer',
            'current_product',
            'current_category',
            'cart_contains_product',
            'cart_contains_category',
            'fulfilment_method',
            'location_city',
            'location_zone',
            'location_available',
            'returning_visitor',
            'new_visitor',
        ];
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
     * @return array{all: list<array<string, mixed>>, any: list<array<string, mixed>>, exclude: list<array<string, mixed>>}
     */
    public function validateTargetingRules(array $rules): array
    {
        $normalized = [
            'all' => $this->normalizeRuleGroup($rules['all'] ?? [], 'targeting_rules.all'),
            'any' => $this->normalizeRuleGroup($rules['any'] ?? [], 'targeting_rules.any'),
            'exclude' => $this->normalizeRuleGroup($rules['exclude'] ?? [], 'targeting_rules.exclude'),
        ];

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array{
     *     placements: list<string>,
     *     category_ids: list<int>,
     *     product_ids: list<int>,
     *     product_tag_ids: list<int>
     * }
     */
    public function validatePlacementRules(array $rules): array
    {
        $placements = $rules['placements'] ?? ['global'];

        if (! is_array($placements) || $placements === []) {
            throw ValidationException::withMessages([
                'placement_rules.placements' => 'At least one placement is required.',
            ]);
        }

        $allowed = CampaignPlacement::values();
        $cleanPlacements = [];

        foreach ($placements as $placement) {
            $placement = (string) $placement;

            if (! in_array($placement, $allowed, true)) {
                throw ValidationException::withMessages([
                    'placement_rules.placements' => 'Invalid placement: '.$placement,
                ]);
            }

            $cleanPlacements[] = $placement;
        }

        return [
            'placements' => array_values(array_unique($cleanPlacements)),
            'category_ids' => $this->normalizeIdList($rules['category_ids'] ?? [], 'placement_rules.category_ids'),
            'product_ids' => $this->normalizeIdList($rules['product_ids'] ?? [], 'placement_rules.product_ids'),
            'product_tag_ids' => $this->normalizeIdList($rules['product_tag_ids'] ?? [], 'placement_rules.product_tag_ids'),
        ];
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array{
     *     type: string,
     *     delay_ms: int|null,
     *     scroll_percent: int|null,
     *     product_view_count: int|null
     * }
     */
    public function validateTriggerRules(array $rules): array
    {
        $type = (string) ($rules['type'] ?? CampaignTriggerType::Immediate->value);

        if (! in_array($type, CampaignTriggerType::values(), true)) {
            throw ValidationException::withMessages([
                'trigger_rules.type' => 'Invalid trigger type.',
            ]);
        }

        $delay = isset($rules['delay_ms']) ? (int) $rules['delay_ms'] : null;
        $scroll = isset($rules['scroll_percent']) ? (int) $rules['scroll_percent'] : null;
        $views = isset($rules['product_view_count']) ? (int) $rules['product_view_count'] : null;

        if ($type === CampaignTriggerType::Delay->value && ($delay === null || $delay < 0 || $delay > 60000)) {
            throw ValidationException::withMessages([
                'trigger_rules.delay_ms' => 'Delay must be between 0 and 60000 ms.',
            ]);
        }

        if ($type === CampaignTriggerType::Scroll->value && ($scroll === null || $scroll < 10 || $scroll > 100)) {
            throw ValidationException::withMessages([
                'trigger_rules.scroll_percent' => 'Scroll percent must be between 10 and 100.',
            ]);
        }

        if ($type === CampaignTriggerType::ProductViews->value && ($views === null || $views < 1 || $views > 20)) {
            throw ValidationException::withMessages([
                'trigger_rules.product_view_count' => 'Product view count must be between 1 and 20.',
            ]);
        }

        return [
            'type' => $type,
            'delay_ms' => $type === CampaignTriggerType::Delay->value ? $delay : null,
            'scroll_percent' => $type === CampaignTriggerType::Scroll->value ? $scroll : null,
            'product_view_count' => $type === CampaignTriggerType::ProductViews->value ? $views : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function assertCtaPayload(array $payload): void
    {
        $type = CampaignCtaType::tryFrom((string) ($payload['cta_type'] ?? ''));

        if ($type === null) {
            throw ValidationException::withMessages([
                'cta_type' => 'Invalid CTA type.',
            ]);
        }

        match ($type) {
            CampaignCtaType::Product => $this->requirePositiveInt($payload['cta_product_id'] ?? null, 'cta_product_id'),
            CampaignCtaType::Category => $this->requirePositiveInt($payload['cta_category_id'] ?? null, 'cta_category_id'),
            CampaignCtaType::Promotion => $this->requirePositiveInt($payload['cta_promotion_id'] ?? null, 'cta_promotion_id'),
            CampaignCtaType::InternalPage => $this->assertInternalPath($payload['cta_internal_path'] ?? null),
            CampaignCtaType::Close => null,
        };
    }

    /**
     * @return list<array{type: string, op: string, value: mixed}>
     */
    protected function normalizeRuleGroup(mixed $group, string $field): array
    {
        if ($group === null || $group === []) {
            return [];
        }

        if (! is_array($group)) {
            throw ValidationException::withMessages([
                $field => 'Rule group must be an array.',
            ]);
        }

        $allowedTypes = $this->allowedRuleTypes();
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

            $normalized[] = [
                'type' => $type,
                'op' => $op,
                'value' => $rule['value'],
            ];
        }

        return $normalized;
    }

    /**
     * @return list<int>
     */
    protected function normalizeIdList(mixed $ids, string $field): array
    {
        if ($ids === null || $ids === []) {
            return [];
        }

        if (! is_array($ids)) {
            throw ValidationException::withMessages([
                $field => 'Must be a list of ids.',
            ]);
        }

        $clean = [];

        foreach ($ids as $id) {
            $id = (int) $id;

            if ($id <= 0) {
                throw ValidationException::withMessages([
                    $field => 'Ids must be positive integers.',
                ]);
            }

            $clean[] = $id;
        }

        return array_values(array_unique($clean));
    }

    protected function requirePositiveInt(mixed $value, string $field): void
    {
        if ((int) $value <= 0) {
            throw ValidationException::withMessages([
                $field => 'A valid target id is required for this CTA.',
            ]);
        }
    }

    protected function assertInternalPath(mixed $path): void
    {
        $path = is_string($path) ? trim($path) : '';

        if ($path === '' || ! str_starts_with($path, '/') || str_contains($path, '://') || str_contains($path, '//')) {
            throw ValidationException::withMessages([
                'cta_internal_path' => 'Internal path must be a relative app path starting with /.',
            ]);
        }

        $allowedPrefixes = ['/menu', '/cart', '/checkout', '/account', '/orders', '/favourites', '/rewards', '/referral', '/dining', '/'];

        $ok = false;

        foreach ($allowedPrefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, rtrim($prefix, '/').'/') || ($prefix === '/' && $path === '/')) {
                $ok = true;
                break;
            }
        }

        if (! $ok && $path !== '/') {
            // Allow exact known roots already covered; reject unknown absolute hosts already blocked.
            if (! preg_match('#^/[a-z0-9/_\\-?=&]*$#i', $path)) {
                throw ValidationException::withMessages([
                    'cta_internal_path' => 'Internal path contains invalid characters.',
                ]);
            }
        }
    }
}
