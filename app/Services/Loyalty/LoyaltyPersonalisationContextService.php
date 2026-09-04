<?php

namespace App\Services\Loyalty;

use App\Enums\LoyaltyRewardType;
use App\Enums\LoyaltyTransactionType;
use App\Enums\UserRole;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyPointTransaction;
use App\Models\LoyaltyReward;
use App\Models\User;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Carbon;
use Throwable;

class LoyaltyPersonalisationContextService implements LoyaltyPersonalisationContextServiceInterface
{
    /** @var array<string, array<string, mixed>> */
    protected array $memo = [];

    public function __construct(
        protected Container $container,
    ) {}

    public function forActor(?User $customer, array $discovery = []): array
    {
        $customer = $customer !== null && $customer->hasRole(UserRole::Customer) ? $customer : null;

        if ($customer === null) {
            return $this->emptyContext(loyaltyEnabled: false);
        }

        $memoKey = 'customer:'.(int) $customer->getKey().':'.md5((string) json_encode([
            'skip' => (bool) ($discovery['skip_discovery'] ?? false),
            'has_available' => array_key_exists('available_now', $discovery),
            'next_id' => $discovery['next_reward']['reward_id'] ?? null,
        ]));

        if (isset($this->memo[$memoKey])) {
            return $this->memo[$memoKey];
        }

        try {
            $context = $this->buildForCustomer($customer, $discovery);
        } catch (Throwable $exception) {
            report($exception);
            $context = $this->emptyContext(
                loyaltyEnabled: (bool) config('loyalty.enabled', false),
                failed: true,
            );
        }

        return $this->memo[$memoKey] = $context;
    }

    public function toCustomerSafeSummary(array $signals): array
    {
        return [
            'loyalty_enabled' => (bool) ($signals['loyalty_enabled'] ?? false),
            'has_loyalty_account' => (bool) ($signals['has_loyalty_account'] ?? false),
            'available_points' => (int) ($signals['available_points'] ?? 0),
            'points_band' => (string) ($signals['points_band'] ?? 'none'),
            'has_affordable_reward' => (bool) ($signals['has_affordable_reward'] ?? false),
            'affordable_reward_count' => (int) ($signals['affordable_reward_count'] ?? 0),
            'reward_available' => (bool) ($signals['has_affordable_reward'] ?? false),
            'nearest_reward_id' => $signals['nearest_reward_id'] ?? null,
            'nearest_reward_points_needed' => $signals['nearest_reward_points_needed'] ?? null,
            'nearest_reward_progress_percent' => $signals['nearest_reward_progress_percent'] ?? null,
            'near_reward' => (bool) ($signals['near_reward'] ?? false),
            'recent_redeemer' => (bool) ($signals['recent_redeemer'] ?? false),
            'recent_earner' => (bool) ($signals['recent_earner'] ?? false),
            'recently_redeemed' => (bool) ($signals['recent_redeemer'] ?? false),
            'loyalty_debt' => (bool) ($signals['loyalty_debt'] ?? false),
            'has_points_debt' => (bool) ($signals['loyalty_debt'] ?? false),
            'redemption_blocked' => (bool) ($signals['redemption_blocked'] ?? false),
            'eligible_product_ids' => array_values(array_map('intval', $signals['eligible_product_ids'] ?? [])),
            'eligible_category_ids' => array_values(array_map('intval', $signals['eligible_category_ids'] ?? [])),
        ];
    }

    public function pointsBand(int $displayPoints): string
    {
        $points = max(0, $displayPoints);
        /** @var array<string, array{min?: int|null, max?: int|null}> $bands */
        $bands = config('loyalty.intelligence.points_bands', [
            'none' => ['min' => 0, 'max' => 0],
            'low' => ['min' => 1, 'max' => 99],
            'medium' => ['min' => 100, 'max' => 499],
            'high' => ['min' => 500, 'max' => null],
        ]);

        foreach (['none', 'low', 'medium', 'high'] as $key) {
            $band = $bands[$key] ?? null;

            if (! is_array($band)) {
                continue;
            }

            $min = array_key_exists('min', $band) && $band['min'] !== null ? (int) $band['min'] : null;
            $max = array_key_exists('max', $band) && $band['max'] !== null ? (int) $band['max'] : null;

            if ($min !== null && $points < $min) {
                continue;
            }

            if ($max !== null && $points > $max) {
                continue;
            }

            return $key;
        }

        return 'none';
    }

    /**
     * @param  array<string, mixed>  $discovery
     * @return array<string, mixed>
     */
    protected function buildForCustomer(User $customer, array $discovery): array
    {
        $loyaltyEnabled = (bool) config('loyalty.enabled', false);
        $redemptionEnabled = $loyaltyEnabled && (bool) config('loyalty.redemption.enabled', true);

        $account = LoyaltyAccount::query()
            ->where('customer_id', $customer->getKey())
            ->first();

        $hasAccount = $account !== null;
        $rawAvailable = (int) ($account?->available_points ?? 0);
        $inDebt = $rawAvailable < 0;
        $displayPoints = max(0, $rawAvailable);
        $redemptionBlocked = $inDebt || ! $redemptionEnabled;

        $availableNow = [];
        $nextReward = null;
        $allCards = [];

        if (array_key_exists('available_now', $discovery)) {
            $availableNow = is_array($discovery['available_now']) ? $discovery['available_now'] : [];
            $nextReward = is_array($discovery['next_reward'] ?? null) ? $discovery['next_reward'] : null;
            $allCards = $availableNow;
        } elseif ($redemptionEnabled && $hasAccount && ! (bool) ($discovery['skip_discovery'] ?? false)) {
            $rewardService = $this->rewardService();
            $allCards = $rewardService->availableRewardsForCustomer($customer, '100000.00');
            $availableNow = array_values(array_filter(
                $allCards,
                static fn (array $reward): bool => (bool) ($reward['eligible'] ?? false),
            ));
            $nextReward = $rewardService->resolveNextRewardProgress(
                $allCards,
                $displayPoints,
                $inDebt,
                $redemptionEnabled,
            );
        }

        $affordableCount = count($availableNow);
        $hasAffordable = $affordableCount > 0;
        $nearestId = isset($nextReward['reward_id']) ? (int) $nextReward['reward_id'] : null;
        $pointsNeeded = isset($nextReward['points_needed']) && $nextReward['points_needed'] !== null
            ? (int) $nextReward['points_needed']
            : null;
        $progressPercent = isset($nextReward['progress_percent'])
            ? (int) $nextReward['progress_percent']
            : null;

        $nearMax = max(0, (int) config('loyalty.intelligence.near_reward_max_points_needed', 50));
        $nearReward = ! $inDebt
            && ! $hasAffordable
            && $pointsNeeded !== null
            && $pointsNeeded > 0
            && $pointsNeeded <= $nearMax;

        $recentEarnDays = max(1, (int) config('loyalty.intelligence.recent_earn_lookback_days', 14));
        $recentRedeemDays = max(1, (int) config('loyalty.intelligence.recent_redeem_lookback_days', 30));

        $recentEarner = $hasAccount && $this->hasRecentTransaction(
            (int) $customer->getKey(),
            LoyaltyTransactionType::Earn,
            $recentEarnDays,
        );
        $recentRedeemer = $hasAccount && $this->hasRecentRedeem((int) $customer->getKey(), $recentRedeemDays);

        if (array_key_exists('recently_redeemed_rewards', $discovery)
            && is_array($discovery['recently_redeemed_rewards'])
            && $discovery['recently_redeemed_rewards'] !== []) {
            $recentRedeemer = true;
        }

        $pointAffordableCards = array_values(array_filter(
            $allCards !== [] ? $allCards : $availableNow,
            static function (array $reward) use ($displayPoints, $inDebt): bool {
                if ($inDebt) {
                    return false;
                }

                $state = (string) ($reward['state'] ?? '');

                if (in_array($state, ['debt', 'scheduled', 'limit_reached', 'disabled'], true)) {
                    return false;
                }

                return (int) ($reward['points_cost'] ?? PHP_INT_MAX) <= $displayPoints;
            },
        ));

        [$eligibleProductIds, $eligibleCategoryIds] = $this->eligibleCatalogIds(
            $pointAffordableCards !== [] ? $pointAffordableCards : $availableNow,
        );

        return [
            'loyalty_enabled' => $loyaltyEnabled,
            'has_loyalty_account' => $hasAccount,
            'available_points' => $displayPoints,
            'available_points_raw' => $rawAvailable,
            'points_band' => $this->pointsBand($displayPoints),
            'has_affordable_reward' => $hasAffordable,
            'affordable_reward_count' => $affordableCount,
            'nearest_reward_id' => $nearestId > 0 ? $nearestId : null,
            'nearest_reward_points_needed' => $pointsNeeded,
            'nearest_reward_progress_percent' => $progressPercent,
            'near_reward' => $nearReward,
            'recent_earner' => $recentEarner,
            'recent_redeemer' => $recentRedeemer,
            'loyalty_debt' => $inDebt,
            'redemption_blocked' => $redemptionBlocked,
            'eligible_product_ids' => $eligibleProductIds,
            'eligible_category_ids' => $eligibleCategoryIds,
            'context_failed' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyContext(bool $loyaltyEnabled, bool $failed = false): array
    {
        return [
            'loyalty_enabled' => $loyaltyEnabled,
            'has_loyalty_account' => false,
            'available_points' => 0,
            'available_points_raw' => 0,
            'points_band' => 'none',
            'has_affordable_reward' => false,
            'affordable_reward_count' => 0,
            'nearest_reward_id' => null,
            'nearest_reward_points_needed' => null,
            'nearest_reward_progress_percent' => null,
            'near_reward' => false,
            'recent_earner' => false,
            'recent_redeemer' => false,
            'loyalty_debt' => false,
            'redemption_blocked' => true,
            'eligible_product_ids' => [],
            'eligible_category_ids' => [],
            'context_failed' => $failed,
        ];
    }

    protected function rewardService(): LoyaltyRewardServiceInterface
    {
        return $this->container->make(LoyaltyRewardServiceInterface::class);
    }

    protected function hasRecentTransaction(int $customerId, LoyaltyTransactionType $type, int $lookbackDays): bool
    {
        $since = Carbon::now()->subDays($lookbackDays);

        return LoyaltyPointTransaction::query()
            ->where('customer_id', $customerId)
            ->where('type', $type->value)
            ->where('occurred_at', '>=', $since)
            ->exists();
    }

    protected function hasRecentRedeem(int $customerId, int $lookbackDays): bool
    {
        $since = Carbon::now()->subDays($lookbackDays);

        return LoyaltyPointTransaction::query()
            ->where('customer_id', $customerId)
            ->where('type', LoyaltyTransactionType::Redeem->value)
            ->where('reason_code', 'order_loyalty_redeem')
            ->where('occurred_at', '>=', $since)
            ->exists();
    }

    /**
     * @param  list<array<string, mixed>>  $availableNow
     * @return array{0: list<int>, 1: list<int>}
     */
    protected function eligibleCatalogIds(array $availableNow): array
    {
        $rewardIds = collect($availableNow)
            ->map(fn (array $row): int => (int) ($row['id'] ?? 0))
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($rewardIds === []) {
            return [[], []];
        }

        $rewards = LoyaltyReward::query()
            ->with(['products:id', 'productCategories:id'])
            ->whereIn('id', $rewardIds)
            ->get();

        $productIds = [];
        $categoryIds = [];

        foreach ($rewards as $reward) {
            $type = $reward->reward_type instanceof LoyaltyRewardType
                ? $reward->reward_type
                : LoyaltyRewardType::tryFrom((string) $reward->reward_type);

            if (! in_array($type, [
                LoyaltyRewardType::FreeBaseProduct,
                LoyaltyRewardType::SpecificProductReward,
                LoyaltyRewardType::CategoryProductReward,
            ], true)) {
                continue;
            }

            foreach ($reward->products as $product) {
                $productIds[] = (int) $product->getKey();
            }

            foreach ($reward->productCategories as $category) {
                $categoryIds[] = (int) $category->getKey();
            }
        }

        return [
            array_values(array_unique($productIds)),
            array_values(array_unique($categoryIds)),
        ];
    }
}
