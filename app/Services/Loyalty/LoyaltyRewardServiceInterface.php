<?php

namespace App\Services\Loyalty;

use App\Models\LoyaltyReward;
use App\Models\User;
use Illuminate\Support\Collection;

interface LoyaltyRewardServiceInterface
{
    public function redemptionEnabled(): bool;

    /**
     * @param  list<array<string, mixed>>  $pricedItems
     * @return list<array<string, mixed>>
     */
    public function availableRewardsForCustomer(User $customer, string $merchandiseAfterPromotions, array $pricedItems = []): array;

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
    ): array;

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
    ): array;

    /**
     * @param  list<array<string, mixed>>  $pricedItems
     */
    public function calculateDiscountAmount(LoyaltyReward $reward, string $merchandiseAfterPromotions, array $pricedItems): string;

    public function activeRewards(): Collection;

    /**
     * @return array<string, mixed>
     */
    public function buildSnapshot(LoyaltyReward $reward, string $discountAmount, int $pointsCost): array;

    public function customerFacingReason(?string $reason): string;
}
