<?php

namespace App\Services\Loyalty;

use App\Enums\LoyaltyRewardStatus;
use App\Enums\LoyaltyRewardType;
use App\Enums\LoyaltyTransactionType;
use App\Models\LoyaltyPointTransaction;
use App\Models\LoyaltyReward;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class LoyaltyRewardService implements LoyaltyRewardServiceInterface
{
    public function __construct(
        protected LoyaltyServiceInterface $loyalty,
    ) {}

    public function redemptionEnabled(): bool
    {
        return (bool) config('loyalty.enabled', false)
            && (bool) config('loyalty.redemption.enabled', true);
    }

    /**
     * @param  list<array<string, mixed>>  $pricedItems
     * @return list<array<string, mixed>>
     */
    public function availableRewardsForCustomer(User $customer, string $merchandiseAfterPromotions, array $pricedItems = []): array
    {
        if (! $this->redemptionEnabled()) {
            return [];
        }

        $account = $this->loyalty->ensureAccount($customer);
        $available = (int) $account->available_points;
        $rewards = $this->activeRewards();

        return $rewards
            ->map(function (LoyaltyReward $reward) use ($customer, $merchandiseAfterPromotions, $pricedItems, $available): array {
                $evaluation = $this->evaluateReward($reward, $customer, $merchandiseAfterPromotions, $pricedItems, $available);

                return [
                    'id' => (int) $reward->getKey(),
                    'name' => (string) $reward->name,
                    'description' => $reward->displayDescription(),
                    'reward_type' => $reward->reward_type instanceof LoyaltyRewardType
                        ? $reward->reward_type->value
                        : (string) $reward->reward_type,
                    'points_cost' => (int) $reward->points_cost,
                    'eligible' => $evaluation['eligible'],
                    'unavailable_reason' => $evaluation['reason'],
                    'preview_discount_amount' => $evaluation['discount_amount'],
                    'minimum_spend' => $reward->minimum_spend !== null
                        ? number_format((float) $reward->minimum_spend, 2, '.', '')
                        : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $pricedItems
     * @return array{
     *     eligible: bool,
     *     reason: string|null,
     *     discount_amount: string,
     *     points_cost: int,
     *     reward: LoyaltyReward|null,
     *     snapshot: array<string, mixed>|null
     * }
     */
    public function evaluateReward(
        LoyaltyReward $reward,
        User $customer,
        string $merchandiseAfterPromotions,
        array $pricedItems,
        ?int $availablePoints = null,
    ): array {
        $reward->loadMissing(['products', 'productCategories', 'addOns']);

        if (! $this->redemptionEnabled()) {
            return $this->evaluation(false, 'redemption_disabled', '0.00', (int) $reward->points_cost, $reward);
        }

        if (! $reward->isRedeemable()) {
            return $this->evaluation(false, 'reward_inactive', '0.00', (int) $reward->points_cost, $reward);
        }

        $pointsCost = (int) $reward->points_cost;

        if ($pointsCost <= 0) {
            return $this->evaluation(false, 'invalid_points_cost', '0.00', $pointsCost, $reward);
        }

        if ($availablePoints === null) {
            $availablePoints = (int) $this->loyalty->ensureAccount($customer)->available_points;
        }

        if ($availablePoints < $pointsCost) {
            $reason = $availablePoints < 0 ? 'points_adjustment_pending' : 'insufficient_points';

            return $this->evaluation(false, $reason, '0.00', $pointsCost, $reward);
        }

        if ($reward->minimum_spend !== null
            && bccomp($merchandiseAfterPromotions, (string) $reward->minimum_spend, 2) < 0) {
            return $this->evaluation(false, 'minimum_spend_not_met', '0.00', $pointsCost, $reward);
        }

        $limitReason = $this->usageLimitReason($reward, $customer);

        if ($limitReason !== null) {
            return $this->evaluation(false, $limitReason, '0.00', $pointsCost, $reward);
        }

        $discount = $this->calculateDiscountAmount($reward, $merchandiseAfterPromotions, $pricedItems);

        if (bccomp($discount, '0', 2) <= 0) {
            return $this->evaluation(false, 'no_applicable_value', '0.00', $pointsCost, $reward);
        }

        $discount = $this->bcMin($discount, $this->bcMaxZero($merchandiseAfterPromotions));

        return [
            'eligible' => true,
            'reason' => null,
            'discount_amount' => $discount,
            'points_cost' => $pointsCost,
            'reward' => $reward,
            'snapshot' => $this->buildSnapshot($reward, $discount, $pointsCost),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $pricedItems
     * @return array{
     *     eligible: bool,
     *     reason: string|null,
     *     discount_amount: string,
     *     points_cost: int,
     *     reward: LoyaltyReward,
     *     snapshot: array<string, mixed>
     * }
     */
    public function assertAndEvaluateForCheckout(
        LoyaltyReward $reward,
        User $customer,
        string $merchandiseAfterPromotions,
        array $pricedItems,
        bool $hasPromotionDiscount = false,
    ): array {
        if (! $this->redemptionEnabled()) {
            throw ValidationException::withMessages([
                'loyalty_reward_id' => 'Loyalty rewards are not available right now.',
            ]);
        }

        if ($hasPromotionDiscount && ! (bool) config('loyalty.redemption.allow_with_promotions', true)) {
            throw ValidationException::withMessages([
                'loyalty_reward_id' => 'Loyalty rewards cannot be combined with promotions.',
            ]);
        }

        $evaluation = $this->evaluateReward($reward, $customer, $merchandiseAfterPromotions, $pricedItems);

        if (! $evaluation['eligible'] || $evaluation['reward'] === null || $evaluation['snapshot'] === null) {
            throw ValidationException::withMessages([
                'loyalty_reward_id' => $this->customerFacingReason($evaluation['reason']),
            ]);
        }

        return $evaluation;
    }

    /**
     * @param  list<array<string, mixed>>  $pricedItems
     */
    public function calculateDiscountAmount(LoyaltyReward $reward, string $merchandiseAfterPromotions, array $pricedItems): string
    {
        $type = $reward->reward_type instanceof LoyaltyRewardType
            ? $reward->reward_type
            : LoyaltyRewardType::tryFrom((string) $reward->reward_type);

        $config = is_array($reward->config) ? $reward->config : [];

        return match ($type) {
            LoyaltyRewardType::FixedOrderDiscount => $this->fixedDiscount($config, $merchandiseAfterPromotions),
            LoyaltyRewardType::PercentageOrderDiscount => $this->percentageDiscount($config, $merchandiseAfterPromotions),
            LoyaltyRewardType::FreeBaseProduct,
            LoyaltyRewardType::SpecificProductReward => $this->freeEligibleBase($reward, $pricedItems, productScoped: true),
            LoyaltyRewardType::CategoryProductReward => $this->freeEligibleBase($reward, $pricedItems, productScoped: false),
            LoyaltyRewardType::FreeAddOn => $this->freeEligibleAddOn($reward, $pricedItems),
            default => '0.00',
        };
    }

    public function activeRewards(): Collection
    {
        return LoyaltyReward::query()
            ->with(['products:id', 'productCategories:id', 'addOns:id'])
            ->where('status', LoyaltyRewardStatus::Active->value)
            ->where(function ($query): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->orderByDesc('priority')
            ->orderBy('points_cost')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function buildSnapshot(LoyaltyReward $reward, string $discountAmount, int $pointsCost): array
    {
        $type = $reward->reward_type instanceof LoyaltyRewardType
            ? $reward->reward_type->value
            : (string) $reward->reward_type;

        return [
            'reward_id' => (int) $reward->getKey(),
            'name' => (string) $reward->name,
            'reward_type' => $type,
            'points_cost' => $pointsCost,
            'discount_amount' => $discountAmount,
            'description' => $reward->displayDescription(),
            'config' => is_array($reward->config) ? $reward->config : [],
            'product_ids' => $reward->products->modelKeys(),
            'product_category_ids' => $reward->productCategories->modelKeys(),
            'add_on_ids' => $reward->addOns->modelKeys(),
            'minimum_spend' => $reward->minimum_spend !== null
                ? number_format((float) $reward->minimum_spend, 2, '.', '')
                : null,
        ];
    }

    public function customerFacingReason(?string $reason): string
    {
        return match ($reason) {
            'insufficient_points' => 'You do not have enough loyalty points for this reward.',
            'points_adjustment_pending' => 'Points adjustment pending. Redemption is unavailable until your balance recovers.',
            'minimum_spend_not_met' => 'Your cart does not meet the minimum spend for this reward.',
            'reward_inactive' => 'That loyalty reward is not available.',
            'no_applicable_value' => 'Add an eligible item to use this reward.',
            'usage_limit_reached' => 'This reward has reached its redemption limit.',
            'customer_usage_limit_reached' => 'You have already used this reward the maximum number of times.',
            'redemption_disabled' => 'Loyalty rewards are not available right now.',
            'promotion_conflict' => 'Loyalty rewards cannot be combined with promotions.',
            'invalid_points_cost' => 'That loyalty reward is misconfigured.',
            default => 'That loyalty reward cannot be applied right now.',
        };
    }

    protected function usageLimitReason(LoyaltyReward $reward, User $customer): ?string
    {
        $rewardId = (int) $reward->getKey();

        if ($reward->usage_limit !== null) {
            $global = $this->netRedemptionCount($rewardId);

            if ($global >= (int) $reward->usage_limit) {
                return 'usage_limit_reached';
            }
        }

        if ($reward->usage_limit_per_customer !== null) {
            $since = null;

            if ($reward->usage_limit_per_customer_period_days !== null) {
                $since = Carbon::now()->subDays((int) $reward->usage_limit_per_customer_period_days);
            }

            $net = $this->netRedemptionCount($rewardId, (int) $customer->getKey(), $since);

            if ($net >= (int) $reward->usage_limit_per_customer) {
                return 'customer_usage_limit_reached';
            }
        }

        return null;
    }

    protected function netRedemptionCount(int $rewardId, ?int $customerId = null, ?Carbon $since = null): int
    {
        $redeems = LoyaltyPointTransaction::query()
            ->where('type', LoyaltyTransactionType::Redeem->value)
            ->where('reason_code', 'order_loyalty_redeem')
            ->where('metadata->reward_id', $rewardId);

        $restores = LoyaltyPointTransaction::query()
            ->where('type', LoyaltyTransactionType::Reversal->value)
            ->where('reason_code', 'order_loyalty_restore')
            ->where('metadata->reward_id', $rewardId);

        if ($customerId !== null) {
            $redeems->where('customer_id', $customerId);
            $restores->where('customer_id', $customerId);
        }

        if ($since !== null) {
            $redeems->where('occurred_at', '>=', $since);
            $restores->where('occurred_at', '>=', $since);
        }

        return max(0, $redeems->count() - $restores->count());
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function fixedDiscount(array $config, string $merchandiseAfterPromotions): string
    {
        $amount = number_format((float) ($config['discount_amount'] ?? 0), 2, '.', '');

        return $this->bcMin($this->bcMaxZero($amount), $this->bcMaxZero($merchandiseAfterPromotions));
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function percentageDiscount(array $config, string $merchandiseAfterPromotions): string
    {
        $percent = number_format((float) ($config['percent'] ?? 0), 4, '.', '');

        if (bccomp($percent, '0', 4) <= 0) {
            return '0.00';
        }

        $raw = bcmul($merchandiseAfterPromotions, bcdiv($percent, '100', 6), 2);

        if (isset($config['maximum_discount_amount']) && $config['maximum_discount_amount'] !== null && $config['maximum_discount_amount'] !== '') {
            $raw = $this->bcMin($raw, number_format((float) $config['maximum_discount_amount'], 2, '.', ''));
        }

        return $this->bcMin($this->bcMaxZero($raw), $this->bcMaxZero($merchandiseAfterPromotions));
    }

    /**
     * @param  list<array<string, mixed>>  $pricedItems
     */
    protected function freeEligibleBase(LoyaltyReward $reward, array $pricedItems, bool $productScoped): string
    {
        $productIds = $reward->products->modelKeys();
        $categoryIds = $reward->productCategories->modelKeys();
        $best = '0.00';

        foreach ($pricedItems as $item) {
            $productId = isset($item['product_id']) ? (int) $item['product_id'] : null;
            $categoryId = isset($item['product_category_id']) ? (int) $item['product_category_id'] : null;
            $base = (string) ($item['base_line_subtotal'] ?? '0.00');

            $matches = false;

            if ($productScoped) {
                $matches = $productIds === [] || ($productId !== null && in_array($productId, $productIds, true));
            } else {
                $matches = ($categoryIds === [] && $productIds === [])
                    || ($productId !== null && in_array($productId, $productIds, true))
                    || ($categoryId !== null && in_array($categoryId, $categoryIds, true));
            }

            if (! $matches) {
                continue;
            }

            if (bccomp($base, $best, 2) > 0) {
                $best = $base;
            }
        }

        return $this->bcMaxZero($best);
    }

    /**
     * @param  list<array<string, mixed>>  $pricedItems
     */
    protected function freeEligibleAddOn(LoyaltyReward $reward, array $pricedItems): string
    {
        $addOnIds = $reward->addOns->modelKeys();
        $best = '0.00';

        foreach ($pricedItems as $item) {
            $addOns = is_array($item['add_ons'] ?? null) ? $item['add_ons'] : [];

            foreach ($addOns as $addOn) {
                $addOnId = isset($addOn['add_on_id']) ? (int) $addOn['add_on_id'] : null;
                $line = (string) ($addOn['line_subtotal'] ?? '0.00');

                if ($addOnIds !== [] && ($addOnId === null || ! in_array($addOnId, $addOnIds, true))) {
                    continue;
                }

                if (bccomp($line, $best, 2) > 0) {
                    $best = $line;
                }
            }

            // Fallback: whole addon_line_subtotal when cart items don't expand add-ons.
            if ($addOns === [] && $addOnIds === []) {
                $addonLine = (string) ($item['addon_line_subtotal'] ?? '0.00');
                if (bccomp($addonLine, $best, 2) > 0) {
                    $best = $addonLine;
                }
            }
        }

        return $this->bcMaxZero($best);
    }

    /**
     * @return array{
     *     eligible: bool,
     *     reason: string|null,
     *     discount_amount: string,
     *     points_cost: int,
     *     reward: LoyaltyReward|null,
     *     snapshot: array<string, mixed>|null
     * }
     */
    protected function evaluation(bool $eligible, ?string $reason, string $discount, int $pointsCost, LoyaltyReward $reward): array
    {
        return [
            'eligible' => $eligible,
            'reason' => $reason,
            'discount_amount' => $discount,
            'points_cost' => $pointsCost,
            'reward' => $eligible ? $reward : $reward,
            'snapshot' => $eligible ? $this->buildSnapshot($reward, $discount, $pointsCost) : null,
        ];
    }

    protected function bcMaxZero(string $amount): string
    {
        return bccomp($amount, '0', 2) < 0 ? '0.00' : number_format((float) $amount, 2, '.', '');
    }

    protected function bcMin(string $a, string $b): string
    {
        return bccomp($a, $b, 2) <= 0 ? number_format((float) $a, 2, '.', '') : number_format((float) $b, 2, '.', '');
    }
}
