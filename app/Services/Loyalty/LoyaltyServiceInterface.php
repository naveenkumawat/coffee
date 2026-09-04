<?php

namespace App\Services\Loyalty;

use App\Models\LoyaltyAccount;
use App\Models\LoyaltyPointTransaction;
use App\Models\Order;
use App\Models\User;

interface LoyaltyServiceInterface
{
    public function ensureAccount(User $customer): LoyaltyAccount;

    /**
     * @return array{
     *     available_points: int,
     *     lifetime_earned_points: int,
     *     lifetime_redeemed_points: int,
     *     lifetime_adjusted_points: int,
     *     has_points_debt: bool,
     *     debt_message: string|null,
     *     earning_enabled: bool,
     *     redemption_enabled: bool,
     *     earning_explanation: string|null,
     *     recent_transactions: list<array<string, mixed>>
     * }
     */
    public function customerPayload(User $customer, int $historyLimit = 20): array;

    /**
     * Eligible merchandise amount (decimal string) used for earning.
     */
    public function eligibleMerchandiseAmount(Order $order): string;

    public function calculateEarnPoints(Order $order): int;

    public function orderIsEligible(Order $order): bool;

    /**
     * @return array{awarded: bool, points: int, transaction_id: int|null, reason: string}
     */
    public function awardForOrder(Order $order): array;

    /**
     * @return array{reversed: bool, points: int, transaction_id: int|null, reason: string}
     */
    public function reverseOrderAward(Order $order, ?string $reasonCode = null): array;

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array{redeemed: bool, points: int, transaction_id: int|null, reason: string}
     */
    public function redeemForOrder(Order $order, int $pointsCost, array $snapshot = []): array;

    /**
     * @return array{restored: bool, points: int, transaction_id: int|null, reason: string}
     */
    public function restoreRedemptionForOrder(Order $order, ?string $reasonCode = null): array;

    /**
     * @return array{adjusted: bool, points: int, transaction_id: int|null, reason: string}
     */
    public function adjustPoints(
        User $customer,
        User $actor,
        int $points,
        string $reason,
        ?string $idempotencyKey = null,
    ): array;

    /**
     * @return array{
     *     points_earned: int|null,
     *     points_redeemed: int|null,
     *     reward_name: string|null,
     *     loyalty_discount_amount: string,
     *     benefit_label: string|null,
     *     earning_pending: bool
     * }
     */
    public function orderFeedback(Order $order): array;

    /**
     * @return list<LoyaltyPointTransaction>
     */
    public function recentTransactions(User $customer, int $limit = 20): array;
}
