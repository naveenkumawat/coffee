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
        protected LoyaltyPersonalisationContextServiceInterface $personalisationContext,
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

        return $this->discoverableRewards()
            ->map(fn (LoyaltyReward $reward): array => $this->toCustomerRewardCard(
                $reward,
                $customer,
                $merchandiseAfterPromotions,
                $pricedItems,
                $available,
            ))
            ->values()
            ->all();
    }

    /**
     * Customer experience payload for the loyalty hub (P3.3).
     *
     * @param  list<array<string, mixed>>  $pricedItems
     * @return array<string, mixed>
     */
    public function customerExperiencePayload(
        User $customer,
        string $merchandiseAfterPromotions = '0.00',
        array $pricedItems = [],
    ): array {
        $account = $this->loyalty->ensureAccount($customer);
        $available = (int) $account->available_points;
        $inDebt = $available < 0;
        $displayPoints = max(0, $available);
        $enabled = $this->redemptionEnabled();

        $discoveryMerchandise = bccomp($merchandiseAfterPromotions, '0', 2) > 0
            ? $merchandiseAfterPromotions
            : '100000.00';

        $rewards = $enabled
            ? $this->availableRewardsForCustomer($customer, $discoveryMerchandise, $pricedItems)
            : [];

        $availableNow = array_values(array_filter($rewards, static fn (array $reward): bool => (bool) $reward['eligible']));
        $locked = array_values(array_filter($rewards, static fn (array $reward): bool => ! (bool) $reward['eligible']));
        $nextReward = $this->resolveNextRewardProgress($rewards, $displayPoints, $inDebt, $enabled);
        $recentlyRedeemed = $this->recentlyRedeemedRewards($customer, 5);
        $signals = $this->personalisationContext->forActor($customer, [
            'available_now' => $availableNow,
            'next_reward' => $nextReward,
            'recently_redeemed_rewards' => $recentlyRedeemed,
        ]);

        return [
            'rewards' => $rewards,
            'available_now' => $availableNow,
            'locked' => $locked,
            'recently_redeemed' => $recentlyRedeemed,
            'next_reward' => $nextReward,
            'personalisation_summary' => $this->personalisationContext->toCustomerSafeSummary($signals),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $pricedItems
     * @return array<string, mixed>
     */
    public function toCustomerRewardCard(
        LoyaltyReward $reward,
        User $customer,
        string $merchandiseAfterPromotions,
        array $pricedItems,
        int $availablePoints,
    ): array {
        $reward->loadMissing(['products:id,name,image_path', 'productCategories:id,name', 'addOns:id,name']);

        $scheduledSoon = $reward->starts_at !== null && now()->lt($reward->starts_at);
        $evaluation = $scheduledSoon
            ? $this->evaluation(false, 'scheduled_not_started', '0.00', (int) $reward->points_cost, $reward)
            : $this->evaluateReward($reward, $customer, $merchandiseAfterPromotions, $pricedItems, $availablePoints);

        $pointsCost = (int) $evaluation['points_cost'];
        $pointsNeeded = max(0, $pointsCost - max(0, $availablePoints));
        $state = $this->rewardUiState($evaluation['eligible'], $evaluation['reason']);
        $benefitLabel = $this->benefitLabel($reward, $evaluation['discount_amount']);

        return [
            'id' => (int) $reward->getKey(),
            'name' => (string) $reward->name,
            'description' => $reward->displayDescription(),
            'reward_type' => $reward->reward_type instanceof LoyaltyRewardType
                ? $reward->reward_type->value
                : (string) $reward->reward_type,
            'points_cost' => $pointsCost,
            'eligible' => (bool) $evaluation['eligible'],
            'state' => $state,
            'unavailable_reason' => $evaluation['reason'],
            'unavailable_message' => $this->customerFacingDetailMessage(
                $evaluation['reason'],
                $pointsNeeded,
                $reward,
            ),
            'points_needed' => $pointsNeeded,
            'preview_discount_amount' => $evaluation['discount_amount'],
            'benefit_label' => $benefitLabel,
            'minimum_spend' => $reward->minimum_spend !== null
                ? number_format((float) $reward->minimum_spend, 2, '.', '')
                : null,
            'starts_at' => $reward->starts_at?->toIso8601String(),
            'ends_at' => $reward->ends_at?->toIso8601String(),
            'image_url' => $this->rewardImageUrl($reward),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rewards
     * @return array<string, mixed>|null
     */
    public function resolveNextRewardProgress(array $rewards, int $displayPoints, bool $inDebt, bool $enabled): ?array
    {
        if (! $enabled) {
            return [
                'state' => 'disabled',
                'reward_id' => null,
                'name' => null,
                'points_cost' => null,
                'points_have' => $displayPoints,
                'points_needed' => null,
                'progress_percent' => 0,
                'message' => 'Loyalty rewards are currently paused.',
            ];
        }

        if ($inDebt) {
            return [
                'state' => 'debt',
                'reward_id' => null,
                'name' => null,
                'points_cost' => null,
                'points_have' => 0,
                'points_needed' => null,
                'progress_percent' => 0,
                'message' => 'Points adjustment pending. Future earned points will restore redemption.',
            ];
        }

        if ($rewards === []) {
            return [
                'state' => 'none',
                'reward_id' => null,
                'name' => null,
                'points_cost' => null,
                'points_have' => $displayPoints,
                'points_needed' => null,
                'progress_percent' => 0,
                'message' => 'No rewards are configured right now.',
            ];
        }

        $ready = collect($rewards)
            ->filter(static fn (array $reward): bool => (bool) ($reward['eligible'] ?? false))
            ->sortBy([
                ['points_cost', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        if ($ready->isNotEmpty()) {
            $reward = $ready->first();

            return [
                'state' => 'ready',
                'reward_id' => (int) $reward['id'],
                'name' => (string) $reward['name'],
                'points_cost' => (int) $reward['points_cost'],
                'points_have' => $displayPoints,
                'points_needed' => 0,
                'progress_percent' => 100,
                'message' => sprintf('You can redeem %s now.', $reward['name']),
            ];
        }

        $lockedByPoints = collect($rewards)
            ->filter(static fn (array $reward): bool => ($reward['unavailable_reason'] ?? null) === 'insufficient_points')
            ->sortBy([
                ['points_cost', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        if ($lockedByPoints->isEmpty()) {
            $firstLocked = collect($rewards)->sortBy([['points_cost', 'asc'], ['id', 'asc']])->first();

            return [
                'state' => 'locked',
                'reward_id' => $firstLocked ? (int) $firstLocked['id'] : null,
                'name' => $firstLocked['name'] ?? null,
                'points_cost' => $firstLocked['points_cost'] ?? null,
                'points_have' => $displayPoints,
                'points_needed' => $firstLocked['points_needed'] ?? null,
                'progress_percent' => 0,
                'message' => $firstLocked['unavailable_message'] ?? 'No rewards are available yet.',
            ];
        }

        $target = $lockedByPoints->first();
        $cost = max(1, (int) $target['points_cost']);
        $needed = max(0, $cost - $displayPoints);
        $percent = (int) min(100, floor(($displayPoints / $cost) * 100));

        return [
            'state' => 'progress',
            'reward_id' => (int) $target['id'],
            'name' => (string) $target['name'],
            'points_cost' => $cost,
            'points_have' => $displayPoints,
            'points_needed' => $needed,
            'progress_percent' => $percent,
            'message' => sprintf('%d more points to unlock %s', $needed, $target['name']),
        ];
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
            ->with(['products:id,name,image_path', 'productCategories:id,name', 'addOns:id,name'])
            ->where('status', LoyaltyRewardStatus::Active->value)
            ->where(function ($query): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->orderByDesc('priority')
            ->orderBy('points_cost')
            ->orderBy('id')
            ->get();
    }

    /**
     * Active rewards including upcoming scheduled rewards (not yet started).
     */
    public function discoverableRewards(): Collection
    {
        return LoyaltyReward::query()
            ->with(['products:id,name,image_path', 'productCategories:id,name', 'addOns:id,name'])
            ->where('status', LoyaltyRewardStatus::Active->value)
            ->where(function ($query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->orderByDesc('priority')
            ->orderBy('points_cost')
            ->orderBy('id')
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
            'benefit_label' => $this->benefitLabel($reward, $discountAmount),
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
            'usage_limit_reached', 'customer_usage_limit_reached' => 'Redemption limit reached.',
            'redemption_disabled' => 'Loyalty rewards are not available right now.',
            'promotion_conflict' => 'Loyalty rewards cannot be combined with promotions.',
            'scheduled_not_started' => 'This reward is not available yet.',
            'invalid_points_cost' => 'That loyalty reward is misconfigured.',
            default => 'That loyalty reward cannot be applied right now.',
        };
    }

    public function customerFacingDetailMessage(?string $reason, int $pointsNeeded, LoyaltyReward $reward): ?string
    {
        return match ($reason) {
            'insufficient_points' => $pointsNeeded > 0
                ? sprintf('%d more points needed', $pointsNeeded)
                : 'You do not have enough loyalty points for this reward.',
            'points_adjustment_pending' => 'Points adjustment pending',
            'minimum_spend_not_met' => $reward->minimum_spend !== null
                ? sprintf('Available on orders above ₹%s', number_format((float) $reward->minimum_spend, 0))
                : 'Minimum spend not met.',
            'scheduled_not_started' => $reward->starts_at !== null
                ? sprintf('Available from %s', $reward->starts_at->timezone(config('app.timezone'))->format('d M Y'))
                : 'Available soon.',
            'usage_limit_reached', 'customer_usage_limit_reached' => 'Redemption limit reached',
            'no_applicable_value' => 'Add an eligible item to unlock this reward',
            'promotion_conflict' => 'Cannot be combined with a promotion',
            null => null,
            default => $this->customerFacingReason($reason),
        };
    }

    public function benefitLabel(LoyaltyReward $reward, string $discountAmount): string
    {
        $type = $reward->reward_type instanceof LoyaltyRewardType
            ? $reward->reward_type
            : LoyaltyRewardType::tryFrom((string) $reward->reward_type);

        $config = is_array($reward->config) ? $reward->config : [];

        return match ($type) {
            LoyaltyRewardType::FreeBaseProduct,
            LoyaltyRewardType::SpecificProductReward,
            LoyaltyRewardType::CategoryProductReward => $this->freeItemBenefitLabel($reward, 'product'),
            LoyaltyRewardType::FreeAddOn => $this->freeItemBenefitLabel($reward, 'add-on'),
            LoyaltyRewardType::PercentageOrderDiscount => sprintf(
                '%s%% off%s',
                rtrim(rtrim(number_format((float) ($config['percent'] ?? 0), 2, '.', ''), '0'), '.'),
                bccomp($discountAmount, '0', 2) > 0 ? ' (about ₹'.number_format((float) $discountAmount, 0).')' : '',
            ),
            LoyaltyRewardType::FixedOrderDiscount => bccomp($discountAmount, '0', 2) > 0
                ? '₹'.number_format((float) $discountAmount, 0).' off'
                : 'Order discount',
            default => bccomp($discountAmount, '0', 2) > 0
                ? '₹'.number_format((float) $discountAmount, 0).' benefit'
                : 'Loyalty reward',
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function recentlyRedeemedRewards(User $customer, int $limit = 5): array
    {
        $rows = LoyaltyPointTransaction::query()
            ->where('customer_id', $customer->getKey())
            ->where('type', LoyaltyTransactionType::Redeem->value)
            ->where('reason_code', 'order_loyalty_redeem')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(max(1, min(20, $limit)))
            ->get();

        return $rows->map(function (LoyaltyPointTransaction $txn): array {
            $metadata = is_array($txn->metadata) ? $txn->metadata : [];

            return [
                'transaction_id' => (int) $txn->getKey(),
                'reward_id' => isset($metadata['reward_id']) ? (int) $metadata['reward_id'] : null,
                'name' => is_string($txn->description) && $txn->description !== ''
                    ? $txn->description
                    : 'Reward redeemed',
                'points' => abs((int) $txn->points),
                'order_number' => isset($metadata['order_number']) ? (string) $metadata['order_number'] : null,
                'occurred_at' => $txn->occurred_at?->toIso8601String(),
            ];
        })->all();
    }

    protected function rewardUiState(bool $eligible, ?string $reason): string
    {
        if ($eligible) {
            return 'available';
        }

        return match ($reason) {
            'usage_limit_reached', 'customer_usage_limit_reached' => 'limit_reached',
            'scheduled_not_started' => 'scheduled',
            'points_adjustment_pending' => 'debt',
            default => 'locked',
        };
    }

    protected function rewardImageUrl(LoyaltyReward $reward): ?string
    {
        $product = $reward->products->first();
        $path = $product?->image_path;

        if (! filled($path)) {
            return null;
        }

        if (str_starts_with((string) $path, 'http://') || str_starts_with((string) $path, 'https://')) {
            return (string) $path;
        }

        return asset('storage/'.$path);
    }

    protected function freeItemBenefitLabel(LoyaltyReward $reward, string $kind): string
    {
        if ($kind === 'add-on') {
            $name = $reward->addOns->first()?->name;

            return $name ? 'Free '.$name : 'Free add-on';
        }

        $productName = $reward->products->first()?->name;
        if ($productName) {
            return 'Free '.$productName;
        }

        $categoryName = $reward->productCategories->first()?->name;

        return $categoryName ? 'Free item from '.$categoryName : 'Free item';
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
