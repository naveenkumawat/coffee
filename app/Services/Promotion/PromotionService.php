<?php

namespace App\Services\Promotion;

use App\Enums\OrderFulfilmentMethod;
use App\Enums\OrderStatus;
use App\Enums\PromotionDiscountType;
use App\Enums\PromotionType;
use App\Models\Promotion;
use App\Models\User;
use App\Services\CafeAvailability\CafeAvailabilityServiceInterface;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PromotionService implements PromotionServiceInterface
{
    public function __construct(
        protected CafeAvailabilityServiceInterface $cafeAvailability,
    ) {}

    public function normalizeCode(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        $normalized = strtoupper(trim($code));

        return $normalized === '' ? null : $normalized;
    }

    /**
     * @param  array{
     *     customer?: ?User,
     *     fulfilment?: ?OrderFulfilmentMethod|string,
     *     promo_code?: ?string,
     *     items: list<array{product_id: ?int, product_category_id: ?int, quantity: int, unit_price: string, line_subtotal: string}>,
     *     at?: ?CarbonInterface
     * }  $context
     * @return array{
     *     discounts: list<array{promotion_id: int, name: string, code: ?string, discount_type: string, discount_value: string, amount: string}>,
     *     discount_total: string,
     *     applied_promo_code: ?string,
     *     promo_error: ?string
     * }
     */
    public function evaluate(array $context): array
    {
        $customer = $context['customer'] ?? null;
        $fulfilment = $this->resolveFulfilment($context['fulfilment'] ?? null);
        $at = isset($context['at']) && $context['at'] instanceof CarbonInterface
            ? CarbonImmutable::parse($context['at'])->timezone($this->cafeAvailability->timezone())
            : $this->cafeAvailability->now();
        $items = $context['items'] ?? [];
        $merchandiseSubtotal = $this->sumLineSubtotals($items);
        $requestedCode = $this->normalizeCode($context['promo_code'] ?? null);

        $candidates = [];
        $promoError = null;

        foreach ($this->activeAutomaticPromotions() as $promotion) {
            $amount = $this->discountAmountIfEligible($promotion, $customer, $fulfilment, $items, $merchandiseSubtotal, $at);

            if ($amount !== null && bccomp($amount, '0', 2) > 0) {
                $candidates[] = $this->candidatePayload($promotion, $amount);
            }
        }

        if ($requestedCode !== null) {
            $coupon = Promotion::query()
                ->where('type', PromotionType::Coupon->value)
                ->where('code', $requestedCode)
                ->first();

            if ($coupon === null || ! $coupon->is_active) {
                $promoError = 'Promo code is not valid.';
            } else {
                $amount = $this->discountAmountIfEligible($coupon, $customer, $fulfilment, $items, $merchandiseSubtotal, $at, true);

                if ($amount === null) {
                    $promoError = $this->customerFacingIneligibilityReason($coupon, $customer, $fulfilment, $items, $merchandiseSubtotal, $at);
                } elseif (bccomp($amount, '0', 2) <= 0) {
                    $promoError = 'Promo code is not valid for this cart.';
                } else {
                    $candidates[] = $this->candidatePayload($coupon, $amount);
                }
            }
        }

        $selected = $this->resolveStacking($candidates);

        return [
            'discounts' => $selected,
            'discount_total' => $this->sumDiscountAmounts($selected),
            'applied_promo_code' => $promoError === null ? $requestedCode : null,
            'promo_error' => $promoError,
        ];
    }

    /**
     * @param  array{
     *     customer?: ?User,
     *     fulfilment?: ?OrderFulfilmentMethod|string,
     *     promo_code?: ?string,
     *     items: list<array{product_id: ?int, product_category_id: ?int, quantity: int, unit_price: string, line_subtotal: string}>,
     *     at?: ?CarbonInterface
     * }  $context
     * @return array{
     *     discounts: list<array{promotion_id: int, name: string, code: ?string, discount_type: string, discount_value: string, amount: string}>,
     *     discount_total: string,
     *     applied_promo_code: ?string
     * }
     */
    public function assertAndEvaluateForCheckout(array $context): array
    {
        $result = $this->evaluate($context);

        if (($result['promo_error'] ?? null) !== null) {
            throw ValidationException::withMessages([
                'promo_code' => $result['promo_error'],
            ]);
        }

        $this->assertUsageSlotsAvailable($result['discounts'], $context['customer'] ?? null);

        return [
            'discounts' => $result['discounts'],
            'discount_total' => $result['discount_total'],
            'applied_promo_code' => $result['applied_promo_code'],
        ];
    }

    /**
     * @param  list<array{promotion_id: int, name: string, code: ?string, discount_type: string, discount_value: string, amount: string}>  $discounts
     */
    public function assertUsageSlotsAvailable(array $discounts, ?User $customer): void
    {
        foreach ($discounts as $discount) {
            /** @var Promotion $promotion */
            $promotion = Promotion::query()->lockForUpdate()->find($discount['promotion_id']);

            if ($promotion === null || ! $promotion->is_active) {
                throw ValidationException::withMessages([
                    'promo_code' => 'Promo code is not valid.',
                ]);
            }

            if ($promotion->usage_limit !== null && $this->usageCount($promotion) >= $promotion->usage_limit) {
                throw ValidationException::withMessages([
                    'promo_code' => 'This offer has reached its usage limit.',
                ]);
            }

            if (
                $customer !== null
                && $promotion->usage_limit_per_customer !== null
                && $this->usageCountForCustomer($promotion, $customer) >= $promotion->usage_limit_per_customer
            ) {
                throw ValidationException::withMessages([
                    'promo_code' => 'You have already used this offer the maximum number of times.',
                ]);
            }
        }
    }

    public function usageCount(Promotion $promotion): int
    {
        return (int) DB::table('order_promotions')
            ->join('orders', 'orders.id', '=', 'order_promotions.order_id')
            ->where('order_promotions.promotion_id', $promotion->getKey())
            ->whereNotIn('orders.status', [
                OrderStatus::Cancelled->value,
                OrderStatus::Rejected->value,
            ])
            ->count();
    }

    public function usageCountForCustomer(Promotion $promotion, User $customer): int
    {
        return (int) DB::table('order_promotions')
            ->join('orders', 'orders.id', '=', 'order_promotions.order_id')
            ->where('order_promotions.promotion_id', $promotion->getKey())
            ->where('orders.customer_id', $customer->getKey())
            ->whereNotIn('orders.status', [
                OrderStatus::Cancelled->value,
                OrderStatus::Rejected->value,
            ])
            ->count();
    }

    /**
     * @return Collection<int, Promotion>
     */
    protected function activeAutomaticPromotions(): Collection
    {
        return Promotion::query()
            ->where('is_active', true)
            ->where('type', PromotionType::Automatic->value)
            ->with(['products:id', 'productCategories:id', 'customers:id'])
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  list<array{product_id: ?int, product_category_id: ?int, quantity: int, unit_price: string, line_subtotal: string}>  $items
     */
    protected function discountAmountIfEligible(
        Promotion $promotion,
        ?User $customer,
        ?OrderFulfilmentMethod $fulfilment,
        array $items,
        string $merchandiseSubtotal,
        CarbonImmutable $at,
        bool $isCouponAttempt = false,
    ): ?string {
        if (! $promotion->is_active) {
            return null;
        }

        if (! $this->isWithinDateWindow($promotion, $at)) {
            return null;
        }

        if (! $this->isWithinDailySchedule($promotion, $at)) {
            return null;
        }

        if (! $this->matchesFulfilment($promotion, $fulfilment)) {
            return null;
        }

        if (! $this->matchesCustomer($promotion, $customer)) {
            return null;
        }

        if ($promotion->first_order_only && ! $this->isFirstOrderCustomer($customer)) {
            return null;
        }

        if (
            $promotion->minimum_subtotal !== null
            && bccomp($merchandiseSubtotal, (string) $promotion->minimum_subtotal, 2) < 0
        ) {
            return null;
        }

        if ($promotion->usage_limit !== null && $this->usageCount($promotion) >= $promotion->usage_limit) {
            return null;
        }

        if (
            $customer !== null
            && $promotion->usage_limit_per_customer !== null
            && $this->usageCountForCustomer($promotion, $customer) >= $promotion->usage_limit_per_customer
        ) {
            return null;
        }

        $eligibleSubtotal = $this->eligibleMerchandiseSubtotal($promotion, $items);

        if (bccomp($eligibleSubtotal, '0', 2) <= 0) {
            return null;
        }

        return $this->calculateDiscountAmount($promotion, $eligibleSubtotal);
    }

    /**
     * @param  list<array{product_id: ?int, product_category_id: ?int, quantity: int, unit_price: string, line_subtotal: string}>  $items
     */
    protected function eligibleMerchandiseSubtotal(Promotion $promotion, array $items): string
    {
        if ($promotion->applies_to_all_products) {
            return $this->sumLineSubtotals($items);
        }

        $productIds = $promotion->relationLoaded('products')
            ? $promotion->products->pluck('id')->map(fn ($id): int => (int) $id)->all()
            : $promotion->products()->pluck('products.id')->map(fn ($id): int => (int) $id)->all();
        $categoryIds = $promotion->relationLoaded('productCategories')
            ? $promotion->productCategories->pluck('id')->map(fn ($id): int => (int) $id)->all()
            : $promotion->productCategories()->pluck('product_categories.id')->map(fn ($id): int => (int) $id)->all();

        $total = '0.00';

        foreach ($items as $item) {
            $productId = isset($item['product_id']) ? (int) $item['product_id'] : null;
            $categoryId = isset($item['product_category_id']) ? (int) $item['product_category_id'] : null;
            $matches = ($productId !== null && in_array($productId, $productIds, true))
                || ($categoryId !== null && in_array($categoryId, $categoryIds, true));

            if (! $matches) {
                continue;
            }

            $total = bcadd($total, $this->normalizeMoney((string) $item['line_subtotal']), 2);
        }

        return $total;
    }

    protected function calculateDiscountAmount(Promotion $promotion, string $eligibleSubtotal): string
    {
        if ($promotion->discount_type === PromotionDiscountType::Fixed) {
            $amount = $this->normalizeMoney((string) $promotion->discount_value);
        } else {
            $raw = bcdiv(bcmul($eligibleSubtotal, (string) $promotion->discount_value, 6), '100', 6);
            $amount = $this->roundMoney($raw);
        }

        if ($promotion->maximum_discount_amount !== null) {
            $cap = $this->normalizeMoney((string) $promotion->maximum_discount_amount);
            if (bccomp($amount, $cap, 2) > 0) {
                $amount = $cap;
            }
        }

        if (bccomp($amount, $eligibleSubtotal, 2) > 0) {
            $amount = $eligibleSubtotal;
        }

        return bccomp($amount, '0', 2) < 0 ? '0.00' : $amount;
    }

    /**
     * @param  list<array{promotion_id: int, name: string, code: ?string, discount_type: string, discount_value: string, amount: string, stackable: bool, priority: int}>  $candidates
     * @return list<array{promotion_id: int, name: string, code: ?string, discount_type: string, discount_value: string, amount: string}>
     */
    protected function resolveStacking(array $candidates): array
    {
        if ($candidates === []) {
            return [];
        }

        $unique = [];
        foreach ($candidates as $candidate) {
            $unique[$candidate['promotion_id']] = $candidate;
        }
        $candidates = array_values($unique);

        $allStackable = collect($candidates)->every(fn (array $c): bool => (bool) $c['stackable']);

        if ($allStackable) {
            usort($candidates, function (array $a, array $b): int {
                return $b['priority'] <=> $a['priority'] ?: $b['amount'] <=> $a['amount'] ?: $a['promotion_id'] <=> $b['promotion_id'];
            });

            return array_map(fn (array $c): array => $this->publicDiscount($c), $candidates);
        }

        usort($candidates, function (array $a, array $b): int {
            $amountCmp = bccomp($b['amount'], $a['amount'], 2);
            if ($amountCmp !== 0) {
                return $amountCmp;
            }

            return $b['priority'] <=> $a['priority'] ?: $a['promotion_id'] <=> $b['promotion_id'];
        });

        return [$this->publicDiscount($candidates[0])];
    }

    /**
     * @param  array{promotion_id: int, name: string, code: ?string, discount_type: string, discount_value: string, amount: string, stackable?: bool, priority?: int}  $candidate
     * @return array{promotion_id: int, name: string, code: ?string, discount_type: string, discount_value: string, amount: string}
     */
    protected function publicDiscount(array $candidate): array
    {
        return [
            'promotion_id' => (int) $candidate['promotion_id'],
            'name' => (string) $candidate['name'],
            'code' => $candidate['code'],
            'discount_type' => (string) $candidate['discount_type'],
            'discount_value' => (string) $candidate['discount_value'],
            'amount' => (string) $candidate['amount'],
        ];
    }

    protected function candidatePayload(Promotion $promotion, string $amount): array
    {
        return [
            'promotion_id' => (int) $promotion->getKey(),
            'name' => $promotion->displayLabel(),
            'code' => $promotion->code,
            'discount_type' => $promotion->discount_type->value,
            'discount_value' => $this->normalizeMoney((string) $promotion->discount_value),
            'amount' => $amount,
            'stackable' => (bool) $promotion->stackable,
            'priority' => (int) $promotion->priority,
        ];
    }

    /**
     * @param  list<array{product_id: ?int, product_category_id: ?int, quantity: int, unit_price: string, line_subtotal: string}>  $items
     */
    protected function customerFacingIneligibilityReason(
        Promotion $promotion,
        ?User $customer,
        ?OrderFulfilmentMethod $fulfilment,
        array $items,
        string $merchandiseSubtotal,
        CarbonImmutable $at,
    ): string {
        if (! $this->isWithinDateWindow($promotion, $at) || ! $this->isWithinDailySchedule($promotion, $at)) {
            return 'This offer is not currently available.';
        }

        if (! $this->matchesFulfilment($promotion, $fulfilment)) {
            return 'This offer is not available for '.($fulfilment?->label() ?? 'this fulfilment').'.';
        }

        if (
            $promotion->minimum_subtotal !== null
            && bccomp($merchandiseSubtotal, (string) $promotion->minimum_subtotal, 2) < 0
        ) {
            return 'Minimum order ₹'.$this->normalizeMoney((string) $promotion->minimum_subtotal).' required.';
        }

        if (! $this->matchesCustomer($promotion, $customer) || ($promotion->first_order_only && ! $this->isFirstOrderCustomer($customer))) {
            return 'This offer is not available for your account.';
        }

        if ($this->eligibleMerchandiseSubtotal($promotion, $items) === '0.00') {
            return 'This offer does not apply to the items in your cart.';
        }

        if ($promotion->usage_limit !== null && $this->usageCount($promotion) >= $promotion->usage_limit) {
            return 'This offer has reached its usage limit.';
        }

        if (
            $customer !== null
            && $promotion->usage_limit_per_customer !== null
            && $this->usageCountForCustomer($promotion, $customer) >= $promotion->usage_limit_per_customer
        ) {
            return 'You have already used this offer the maximum number of times.';
        }

        return 'Promo code is not valid.';
    }

    protected function isWithinDateWindow(Promotion $promotion, CarbonImmutable $at): bool
    {
        $utc = $at->timezone('UTC');

        if ($promotion->starts_at !== null && $utc->lt($promotion->starts_at)) {
            return false;
        }

        if ($promotion->ends_at !== null && $utc->gte($promotion->ends_at)) {
            return false;
        }

        return true;
    }

    protected function isWithinDailySchedule(Promotion $promotion, CarbonImmutable $at): bool
    {
        $weekdays = $promotion->weekdays;

        if (is_array($weekdays) && $weekdays !== [] && ! in_array($at->dayOfWeek, array_map('intval', $weekdays), true)) {
            return false;
        }

        $starts = $promotion->daily_starts_at;
        $ends = $promotion->daily_ends_at;

        if ($starts === null && $ends === null) {
            return true;
        }

        $current = sprintf('%02d:%02d:%02d', $at->hour, $at->minute, $at->second);
        $start = is_string($starts) ? substr($starts, 0, 8) : null;
        $end = is_string($ends) ? substr($ends, 0, 8) : null;

        if ($start !== null && $current < $start) {
            return false;
        }

        if ($end !== null && $current >= $end) {
            return false;
        }

        return true;
    }

    protected function matchesFulfilment(Promotion $promotion, ?OrderFulfilmentMethod $fulfilment): bool
    {
        $context = $fulfilment?->value;

        return $promotion->fulfilment_scope->matchesContext($context);
    }

    protected function matchesCustomer(Promotion $promotion, ?User $customer): bool
    {
        if ($promotion->applies_to_all_customers) {
            return true;
        }

        if ($customer === null) {
            return false;
        }

        if ($promotion->relationLoaded('customers')) {
            return $promotion->customers->contains('id', $customer->getKey());
        }

        return $promotion->customers()->where('users.id', $customer->getKey())->exists();
    }

    protected function isFirstOrderCustomer(?User $customer): bool
    {
        if ($customer === null) {
            return false;
        }

        return ! DB::table('orders')
            ->where('customer_id', $customer->getKey())
            ->whereNotIn('status', [
                OrderStatus::Cancelled->value,
                OrderStatus::Rejected->value,
            ])
            ->exists();
    }

    protected function resolveFulfilment(null|OrderFulfilmentMethod|string $fulfilment): ?OrderFulfilmentMethod
    {
        if ($fulfilment instanceof OrderFulfilmentMethod) {
            return $fulfilment;
        }

        if (! is_string($fulfilment) || $fulfilment === '') {
            return null;
        }

        return OrderFulfilmentMethod::tryFrom($fulfilment);
    }

    /**
     * @param  list<array{line_subtotal: string}>  $items
     */
    protected function sumLineSubtotals(array $items): string
    {
        $total = '0.00';

        foreach ($items as $item) {
            $total = bcadd($total, $this->normalizeMoney((string) ($item['line_subtotal'] ?? '0')), 2);
        }

        return $total;
    }

    /**
     * @param  list<array{amount: string}>  $discounts
     */
    protected function sumDiscountAmounts(array $discounts): string
    {
        $total = '0.00';

        foreach ($discounts as $discount) {
            $total = bcadd($total, $this->normalizeMoney((string) $discount['amount']), 2);
        }

        return $total;
    }

    protected function normalizeMoney(string $value): string
    {
        if (! is_numeric($value)) {
            return '0.00';
        }

        return $this->roundMoney($value);
    }

    protected function roundMoney(string $value): string
    {
        $negative = bccomp($value, '0', 8) < 0;
        $absolute = $negative ? bcmul($value, '-1', 8) : $value;
        $scaled = bcmul($absolute, '100', 4);
        $rounded = bcadd($scaled, '0.5', 0);
        $result = bcdiv($rounded, '100', 2);

        return $negative ? bcmul($result, '-1', 2) : $result;
    }
}
