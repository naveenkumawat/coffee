<?php

namespace App\Services\Targeting;

use App\Enums\OrderStatus;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TargetingRuleEvaluator
{
    /**
     * @param  array{all?: list<array<string, mixed>>, any?: list<array<string, mixed>>, exclude?: list<array<string, mixed>>}  $rules
     * @param  array<string, mixed>  $context
     * @param  callable(int): bool|null  $segmentResolver
     */
    public function matchesGroups(array $rules, array $context, ?callable $segmentResolver = null): bool
    {
        $all = is_array($rules['all'] ?? null) ? $rules['all'] : [];
        $any = is_array($rules['any'] ?? null) ? $rules['any'] : [];
        $exclude = is_array($rules['exclude'] ?? null) ? $rules['exclude'] : [];

        foreach ($exclude as $rule) {
            if ($this->evaluateRule(is_array($rule) ? $rule : [], $context, $segmentResolver)) {
                return false;
            }
        }

        foreach ($all as $rule) {
            if (! $this->evaluateRule(is_array($rule) ? $rule : [], $context, $segmentResolver)) {
                return false;
            }
        }

        if ($any === []) {
            return true;
        }

        foreach ($any as $rule) {
            if ($this->evaluateRule(is_array($rule) ? $rule : [], $context, $segmentResolver)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $rule
     * @param  array<string, mixed>  $context
     * @param  callable(int): bool|null  $segmentResolver
     */
    public function evaluateRule(array $rule, array $context, ?callable $segmentResolver = null): bool
    {
        $type = (string) ($rule['type'] ?? '');
        $op = (string) ($rule['op'] ?? 'eq');
        $value = $rule['value'] ?? null;
        $profile = is_array($context['profile'] ?? null) ? $context['profile'] : [];
        /** @var User|null $customer */
        $customer = $context['customer'] ?? null;

        if ($type === 'segment_matches') {
            if ($segmentResolver === null) {
                return false;
            }

            return $segmentResolver((int) $value) === true;
        }

        if ($type === 'segment_not_matches') {
            if ($segmentResolver === null) {
                return false;
            }

            return $segmentResolver((int) $value) === false;
        }

        $actual = match ($type) {
            'identity' => $customer !== null ? 'authenticated' : 'guest',
            'has_sufficient_evidence' => (bool) ($profile['has_sufficient_evidence'] ?? false),
            'category_affinity' => $this->affinityIds($profile, 'category_affinities'),
            'product_affinity' => $this->affinityIds($profile, 'product_affinities'),
            'flavour_affinity' => $this->affinityIds($profile, 'flavour_affinities'),
            'favourite_product' => $context['favourite_ids'] ?? [],
            'has_favourites' => count($context['favourite_ids'] ?? []) > 0,
            'favourite_count' => count($context['favourite_ids'] ?? []),
            'previous_purchase' => $context['purchased_product_ids'] ?? $this->purchasedProductIds($customer),
            'repeat_purchase' => array_map('intval', $profile['repeat_purchase_product_ids'] ?? []),
            'purchased_category' => $context['purchased_category_ids'] ?? $this->purchasedCategoryIds($customer),
            'recent_product' => array_map('intval', $profile['recent_product_ids'] ?? []),
            'recent_category' => array_map('intval', $profile['recent_category_ids'] ?? []),
            'min_interactions' => (int) ($profile['event_sample_count'] ?? 0),
            'spend_band' => (string) (($profile['spend_band']['band'] ?? '') ?: ''),
            'time_of_day' => $this->topTimeOfDay($profile),
            'completed_orders' => (int) ($context['completed_order_count'] ?? 0),
            'first_order' => (int) ($context['completed_order_count'] ?? 0) === 0,
            'returning_buyer' => (int) ($context['completed_order_count'] ?? 0) >= 1,
            'last_purchase_days' => $context['last_purchase_days'] ?? $this->lastPurchaseDays($customer),
            'orders_per_30d' => $this->ordersPer30d($profile),
            'days_since_activity' => $this->daysSinceActivity($profile),
            'current_product' => (int) ($context['product_id'] ?? 0),
            'current_category' => (int) ($context['category_id'] ?? 0),
            'cart_contains_product' => $context['cart_product_ids'] ?? [],
            'cart_contains_category' => $context['cart_category_ids']
                ?? $this->cartCategoryIds($context['cart_product_ids'] ?? []),
            'fulfilment_method' => (string) ($context['fulfilment_method'] ?? ''),
            'location_city' => (string) ($context['location_city'] ?? ''),
            'location_zone' => (string) ($context['location_zone'] ?? ''),
            'location_available' => (bool) ($context['location_available'] ?? false),
            'returning_visitor' => (bool) ($context['is_returning_visitor'] ?? false),
            'new_visitor' => ! (bool) ($context['is_returning_visitor'] ?? false),
            default => null,
        };

        if ($type === 'identity' && in_array((string) $value, ['everyone', 'all'], true)) {
            return true;
        }

        if ($type === 'identity' && (string) $value === 'new_visitor') {
            return $customer === null && ! (bool) ($context['is_returning_visitor'] ?? false);
        }

        if ($type === 'identity' && (string) $value === 'returning_visitor') {
            return $customer === null && (bool) ($context['is_returning_visitor'] ?? false);
        }

        if ($type === 'identity' && (string) $value === 'new_customer') {
            return $customer !== null && (int) ($context['completed_order_count'] ?? 0) === 0;
        }

        if ($type === 'identity' && (string) $value === 'returning_customer') {
            return $customer !== null && (int) ($context['completed_order_count'] ?? 0) >= 1;
        }

        // Fail closed when required evidence is missing.
        if (in_array($type, ['last_purchase_days', 'orders_per_30d', 'days_since_activity'], true) && $actual === null) {
            return false;
        }

        if (in_array($type, ['location_city', 'location_zone'], true) && ! (bool) ($context['location_available'] ?? false)) {
            return false;
        }

        if ($actual === null && $type !== '') {
            return false;
        }

        return $this->compare($actual, $op, $value);
    }

    protected function compare(mixed $actual, string $op, mixed $expected): bool
    {
        return match ($op) {
            'eq' => is_array($actual)
                ? in_array($this->normalizeScalar($expected), array_map([$this, 'normalizeScalar'], $actual), true)
                : $this->normalizeScalar($actual) === $this->normalizeScalar($expected),
            'neq' => ! $this->compare($actual, 'eq', $expected),
            'gte' => (float) $actual >= (float) $expected,
            'lte' => (float) $actual <= (float) $expected,
            'gt' => (float) $actual > (float) $expected,
            'lt' => (float) $actual < (float) $expected,
            'includes', 'in' => is_array($actual)
                ? in_array($this->normalizeScalar($expected), array_map([$this, 'normalizeScalar'], $actual), true)
                : false,
            'excludes', 'not_in' => is_array($actual)
                ? ! in_array($this->normalizeScalar($expected), array_map([$this, 'normalizeScalar'], $actual), true)
                : true,
            default => false,
        };
    }

    protected function normalizeScalar(mixed $value): string|int|bool|float
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return str_contains((string) $value, '.') ? (float) $value : (int) $value;
        }

        return strtolower(trim((string) $value));
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return list<int>
     */
    public function affinityIds(array $profile, string $key): array
    {
        $rows = $profile[$key] ?? [];

        if (! is_array($rows)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($row): int => (int) (is_array($row) ? ($row['id'] ?? 0) : $row),
            $rows,
        )));
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    public function topTimeOfDay(array $profile): string
    {
        $rows = $profile['time_of_day_preferences'] ?? [];

        if (! is_array($rows) || $rows === []) {
            return '';
        }

        $first = $rows[0] ?? null;

        if (is_array($first)) {
            return (string) ($first['key'] ?? $first['id'] ?? '');
        }

        return (string) $first;
    }

    /**
     * @return list<int>
     */
    public function purchasedProductIds(?User $customer): array
    {
        if ($customer === null) {
            return [];
        }

        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.customer_id', $customer->getKey())
            ->where('orders.status', OrderStatus::Completed->value)
            ->distinct()
            ->pluck('order_items.product_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @return list<int>
     */
    public function purchasedCategoryIds(?User $customer): array
    {
        if ($customer === null) {
            return [];
        }

        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.customer_id', $customer->getKey())
            ->where('orders.status', OrderStatus::Completed->value)
            ->whereNotNull('products.product_category_id')
            ->distinct()
            ->pluck('products.product_category_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @param  list<int>  $productIds
     * @return list<int>
     */
    public function cartCategoryIds(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        return Product::query()
            ->whereIn('id', $productIds)
            ->pluck('product_category_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function completedOrderCount(?User $customer): int
    {
        if ($customer === null) {
            return 0;
        }

        return (int) DB::table('orders')
            ->where('customer_id', $customer->getKey())
            ->where('status', OrderStatus::Completed->value)
            ->count();
    }

    public function lastPurchaseDays(?User $customer): ?int
    {
        if ($customer === null) {
            return null;
        }

        $completedAt = DB::table('orders')
            ->where('customer_id', $customer->getKey())
            ->where('status', OrderStatus::Completed->value)
            ->orderByDesc('completed_at')
            ->value('completed_at');

        if ($completedAt === null) {
            return null;
        }

        return (int) Carbon::parse($completedAt)->diffInDays(now());
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    public function ordersPer30d(array $profile): ?float
    {
        $frequency = is_array($profile['purchase_frequency'] ?? null) ? $profile['purchase_frequency'] : [];

        if (! ($frequency['sufficient'] ?? false) || ! isset($frequency['orders_per_30d'])) {
            return null;
        }

        return (float) $frequency['orders_per_30d'];
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    public function daysSinceActivity(array $profile): ?int
    {
        $last = $profile['last_activity_at'] ?? null;

        if (! is_string($last) || $last === '') {
            return null;
        }

        try {
            return (int) Carbon::parse($last)->diffInDays(now());
        } catch (\Throwable) {
            return null;
        }
    }

    public function isReturningVisitor(?string $visitorKey, ?User $customer): bool
    {
        if ($customer !== null) {
            return $this->completedOrderCount($customer) > 0
                || (int) DB::table('customer_behaviour_events')->where('customer_id', $customer->getKey())->count() > 1;
        }

        if ($visitorKey === null) {
            return false;
        }

        return (int) DB::table('customer_behaviour_events')
            ->where('visitor_key', $visitorKey)
            ->whereNull('customer_id')
            ->count() > 1;
    }
}
