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
     *     earning_enabled: bool,
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
     * @return list<LoyaltyPointTransaction>
     */
    public function recentTransactions(User $customer, int $limit = 20): array;
}
