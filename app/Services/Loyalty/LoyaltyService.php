<?php

namespace App\Services\Loyalty;

use App\Enums\LoyaltyTransactionSourceType;
use App\Enums\LoyaltyTransactionType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyPointTransaction;
use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class LoyaltyService implements LoyaltyServiceInterface
{
    public function ensureAccount(User $customer): LoyaltyAccount
    {
        if (! $customer->hasRole(UserRole::Customer)) {
            throw new \InvalidArgumentException('Loyalty accounts are only available for customers.');
        }

        $account = LoyaltyAccount::query()->where('customer_id', $customer->getKey())->first();

        if ($account !== null) {
            return $account;
        }

        return LoyaltyAccount::query()->firstOrCreate(
            ['customer_id' => $customer->getKey()],
            [
                'available_points' => 0,
                'lifetime_earned_points' => 0,
                'lifetime_redeemed_points' => 0,
                'lifetime_adjusted_points' => 0,
                'version' => 1,
            ],
        );
    }

    public function customerPayload(User $customer, int $historyLimit = 20): array
    {
        $account = $this->ensureAccount($customer);
        $limit = max(1, min(50, $historyLimit));
        $available = (int) $account->available_points;
        $inDebt = $available < 0;

        return [
            'available_points' => $available,
            'lifetime_earned_points' => (int) $account->lifetime_earned_points,
            'lifetime_redeemed_points' => (int) $account->lifetime_redeemed_points,
            'lifetime_adjusted_points' => (int) $account->lifetime_adjusted_points,
            'has_points_debt' => $inDebt,
            'debt_message' => $inDebt ? 'Points adjustment pending' : null,
            'earning_enabled' => $this->isEnabled(),
            'redemption_enabled' => $this->isEnabled() && (bool) config('loyalty.redemption.enabled', true),
            'earning_explanation' => $this->customerExplanation(),
            'recent_transactions' => collect($this->recentTransactions($customer, $limit))
                ->map(fn (LoyaltyPointTransaction $txn): array => $this->toCustomerTransaction($txn))
                ->all(),
        ];
    }

    public function eligibleMerchandiseAmount(Order $order): string
    {
        // V1 policy: pre-tax merchandise after discounts; exclude delivery fee and tax.
        // Prefer canonical taxable_amount (GST basis / merchandise after promo+referral coupon).
        $taxable = $order->taxable_amount !== null ? (string) $order->taxable_amount : null;

        if ($taxable !== null && bccomp($taxable, '0', 2) > 0) {
            return $this->bcMaxZero($taxable);
        }

        $subtotal = (string) ($order->subtotal ?? '0');
        $discount = (string) ($order->discount_total ?? '0');
        $net = bcsub($subtotal, $discount, 2);

        return $this->bcMaxZero($net);
    }

    public function calculateEarnPoints(Order $order): int
    {
        if (! $this->isEnabled()) {
            return 0;
        }

        $eligible = $this->eligibleMerchandiseAmount($order);
        $minimum = $this->decimalConfig('loyalty.earning.minimum_eligible_amount', '0');

        if (bccomp($eligible, $minimum, 2) < 0) {
            return 0;
        }

        $currencyUnit = $this->decimalConfig('loyalty.earning.currency_unit', '1');
        $pointsPerUnit = max(0, (int) config('loyalty.earning.points_per_currency_unit', 0));

        if ($pointsPerUnit <= 0 || bccomp($currencyUnit, '0', 4) <= 0) {
            return 0;
        }

        // points = floor(eligible / currency_unit) * points_per_currency_unit (deterministic).
        $units = bcdiv($eligible, $currencyUnit, 0);
        $wholeUnits = max(0, (int) $units);

        return max(0, $wholeUnits * $pointsPerUnit);
    }

    public function orderIsEligible(Order $order): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        if ($order->customer_id === null) {
            return false;
        }

        $status = $order->status instanceof OrderStatus
            ? $order->status
            : OrderStatus::tryFrom((string) $order->status);

        if ($status !== OrderStatus::Completed) {
            return false;
        }

        if (! $this->paymentIsConfirmed($order)) {
            return false;
        }

        if (! $this->isOnOrAfterEffectiveAt($order)) {
            return false;
        }

        return $this->calculateEarnPoints($order) > 0;
    }

    public function awardForOrder(Order $order): array
    {
        $order = $order->fresh(['diningSession']) ?? $order;
        $idempotencyKey = $this->earnIdempotencyKey((int) $order->getKey());

        if (! $this->isEnabled()) {
            return $this->awardResult(false, 0, null, 'disabled');
        }

        if ($order->customer_id === null) {
            return $this->awardResult(false, 0, null, 'guest');
        }

        $existing = LoyaltyPointTransaction::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing !== null) {
            return $this->awardResult(true, (int) $existing->points, (int) $existing->getKey(), 'idempotent');
        }

        $status = $order->status instanceof OrderStatus
            ? $order->status
            : OrderStatus::tryFrom((string) $order->status);

        if ($status !== OrderStatus::Completed) {
            return $this->awardResult(false, 0, null, 'incomplete');
        }

        if (! $this->paymentIsConfirmed($order)) {
            return $this->awardResult(false, 0, null, 'unpaid');
        }

        if (! $this->isOnOrAfterEffectiveAt($order)) {
            return $this->awardResult(false, 0, null, 'before_effective_at');
        }

        if (in_array($status, [OrderStatus::Cancelled, OrderStatus::Rejected], true)) {
            return $this->awardResult(false, 0, null, 'excluded_status');
        }

        $points = $this->calculateEarnPoints($order);

        if ($points <= 0) {
            return $this->awardResult(false, 0, null, 'zero_points');
        }

        $customer = User::query()->find($order->customer_id);

        if ($customer === null || ! $customer->hasRole(UserRole::Customer)) {
            return $this->awardResult(false, 0, null, 'invalid_customer');
        }

        try {
            $transaction = DB::transaction(function () use ($customer, $order, $points, $idempotencyKey): LoyaltyPointTransaction {
                $account = LoyaltyAccount::query()
                    ->where('customer_id', $customer->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($account === null) {
                    $this->ensureAccount($customer);
                    $account = LoyaltyAccount::query()
                        ->where('customer_id', $customer->getKey())
                        ->lockForUpdate()
                        ->firstOrFail();
                }

                $duplicate = LoyaltyPointTransaction::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($duplicate !== null) {
                    return $duplicate;
                }

                $transaction = LoyaltyPointTransaction::query()->create([
                    'loyalty_account_id' => $account->getKey(),
                    'customer_id' => $customer->getKey(),
                    'type' => LoyaltyTransactionType::Earn,
                    'points' => $points,
                    'source_type' => LoyaltyTransactionSourceType::Order,
                    'source_id' => (int) $order->getKey(),
                    'idempotency_key' => $idempotencyKey,
                    'reason_code' => 'order_completed',
                    'description' => sprintf('Earned for order %s', $order->order_number ?? $order->getKey()),
                    'metadata' => [
                        'eligible_amount' => $this->eligibleMerchandiseAmount($order),
                        'order_number' => $order->order_number,
                        'fulfilment_method' => $order->fulfilment_method,
                    ],
                    'occurred_at' => $order->completed_at ?? now(),
                ]);

                $account->forceFill([
                    'available_points' => (int) $account->available_points + $points,
                    'lifetime_earned_points' => (int) $account->lifetime_earned_points + $points,
                    'version' => (int) $account->version + 1,
                ])->save();

                return $transaction;
            });
        } catch (Throwable $exception) {
            // Concurrent insert on unique key — treat as idempotent success.
            $existing = LoyaltyPointTransaction::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing !== null) {
                return $this->awardResult(true, (int) $existing->points, (int) $existing->getKey(), 'idempotent');
            }

            Log::error('loyalty.award_failed', [
                'order_id' => $order->getKey(),
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        return $this->awardResult(true, (int) $transaction->points, (int) $transaction->getKey(), 'awarded');
    }

    public function reverseOrderAward(Order $order, ?string $reasonCode = null): array
    {
        $orderId = (int) $order->getKey();
        $earnKey = $this->earnIdempotencyKey($orderId);
        $reversalKey = $this->reversalIdempotencyKey($orderId);

        $existingReversal = LoyaltyPointTransaction::query()
            ->where('idempotency_key', $reversalKey)
            ->first();

        if ($existingReversal !== null) {
            return $this->reversalResult(true, abs((int) $existingReversal->points), (int) $existingReversal->getKey(), 'idempotent');
        }

        $earn = LoyaltyPointTransaction::query()
            ->where('idempotency_key', $earnKey)
            ->where('type', LoyaltyTransactionType::Earn->value)
            ->first();

        if ($earn === null) {
            return $this->reversalResult(false, 0, null, 'no_earn');
        }

        $points = abs((int) $earn->points);

        if ($points <= 0) {
            return $this->reversalResult(false, 0, null, 'zero_points');
        }

        try {
            $transaction = DB::transaction(function () use ($earn, $order, $points, $reversalKey, $reasonCode): LoyaltyPointTransaction {
                $duplicate = LoyaltyPointTransaction::query()
                    ->where('idempotency_key', $reversalKey)
                    ->lockForUpdate()
                    ->first();

                if ($duplicate !== null) {
                    return $duplicate;
                }

                $account = LoyaltyAccount::query()
                    ->whereKey($earn->loyalty_account_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                // P3.2 debt policy: earn reversals may drive available_points negative
                // when points were already spent. Never silently clamp.
                $transaction = LoyaltyPointTransaction::query()->create([
                    'loyalty_account_id' => $account->getKey(),
                    'customer_id' => $earn->customer_id,
                    'type' => LoyaltyTransactionType::Reversal,
                    'points' => -$points,
                    'source_type' => LoyaltyTransactionSourceType::Order,
                    'source_id' => (int) $order->getKey(),
                    'idempotency_key' => $reversalKey,
                    'reason_code' => $reasonCode ?: 'order_earn_reversal',
                    'description' => sprintf('Reversal for order %s', $order->order_number ?? $order->getKey()),
                    'metadata' => [
                        'reversed_transaction_id' => (int) $earn->getKey(),
                        'order_number' => $order->order_number,
                        'created_debt' => (int) $account->available_points < $points,
                    ],
                    'occurred_at' => now(),
                ]);

                // lifetime_earned_points is historical and is not reduced by reversals.
                $account->forceFill([
                    'available_points' => (int) $account->available_points - $points,
                    'version' => (int) $account->version + 1,
                ])->save();

                return $transaction;
            });
        } catch (Throwable $exception) {
            $existing = LoyaltyPointTransaction::query()
                ->where('idempotency_key', $reversalKey)
                ->first();

            if ($existing !== null) {
                return $this->reversalResult(true, abs((int) $existing->points), (int) $existing->getKey(), 'idempotent');
            }

            throw $exception;
        }

        return $this->reversalResult(true, $points, (int) $transaction->getKey(), 'reversed');
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array{redeemed: bool, points: int, transaction_id: int|null, reason: string}
     */
    public function redeemForOrder(Order $order, int $pointsCost, array $snapshot = []): array
    {
        $orderId = (int) $order->getKey();
        $rewardId = (int) ($snapshot['reward_id'] ?? $order->loyalty_reward_id ?? 0);
        $idempotencyKey = $this->redeemIdempotencyKey($orderId, $rewardId);

        $existing = LoyaltyPointTransaction::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existing !== null) {
            return $this->redeemResult(true, abs((int) $existing->points), (int) $existing->getKey(), 'idempotent');
        }

        if ($order->customer_id === null) {
            return $this->redeemResult(false, 0, null, 'guest');
        }

        if ($pointsCost <= 0 || $rewardId <= 0) {
            return $this->redeemResult(false, 0, null, 'invalid');
        }

        $customer = User::query()->find($order->customer_id);

        if ($customer === null || ! $customer->hasRole(UserRole::Customer)) {
            return $this->redeemResult(false, 0, null, 'invalid_customer');
        }

        try {
            $transaction = DB::transaction(function () use ($customer, $order, $pointsCost, $rewardId, $idempotencyKey, $snapshot): LoyaltyPointTransaction {
                $duplicate = LoyaltyPointTransaction::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($duplicate !== null) {
                    return $duplicate;
                }

                $account = LoyaltyAccount::query()
                    ->where('customer_id', $customer->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($account === null) {
                    $this->ensureAccount($customer);
                    $account = LoyaltyAccount::query()
                        ->where('customer_id', $customer->getKey())
                        ->lockForUpdate()
                        ->firstOrFail();
                }

                if ((int) $account->available_points < $pointsCost) {
                    throw new \RuntimeException('loyalty.insufficient_points');
                }

                $transaction = LoyaltyPointTransaction::query()->create([
                    'loyalty_account_id' => $account->getKey(),
                    'customer_id' => $customer->getKey(),
                    'type' => LoyaltyTransactionType::Redeem,
                    'points' => -$pointsCost,
                    'source_type' => LoyaltyTransactionSourceType::Order,
                    'source_id' => (int) $order->getKey(),
                    'idempotency_key' => $idempotencyKey,
                    'reason_code' => 'order_loyalty_redeem',
                    'description' => sprintf(
                        'Redeemed for %s on order %s',
                        (string) ($snapshot['name'] ?? 'reward'),
                        $order->order_number ?? $order->getKey(),
                    ),
                    'metadata' => [
                        'reward_id' => $rewardId,
                        'discount_amount' => $snapshot['discount_amount'] ?? null,
                        'reward_type' => $snapshot['reward_type'] ?? null,
                        'order_number' => $order->order_number,
                    ],
                    'occurred_at' => $order->placed_at ?? now(),
                ]);

                $account->forceFill([
                    'available_points' => (int) $account->available_points - $pointsCost,
                    'lifetime_redeemed_points' => (int) $account->lifetime_redeemed_points + $pointsCost,
                    'version' => (int) $account->version + 1,
                ])->save();

                return $transaction;
            });
        } catch (Throwable $exception) {
            $existing = LoyaltyPointTransaction::query()->where('idempotency_key', $idempotencyKey)->first();

            if ($existing !== null) {
                return $this->redeemResult(true, abs((int) $existing->points), (int) $existing->getKey(), 'idempotent');
            }

            if ($exception->getMessage() === 'loyalty.insufficient_points') {
                return $this->redeemResult(false, 0, null, 'insufficient_points');
            }

            throw $exception;
        }

        return $this->redeemResult(true, $pointsCost, (int) $transaction->getKey(), 'redeemed');
    }

    /**
     * @return array{restored: bool, points: int, transaction_id: int|null, reason: string}
     */
    public function restoreRedemptionForOrder(Order $order, ?string $reasonCode = null): array
    {
        $orderId = (int) $order->getKey();
        $rewardId = (int) ($order->loyalty_reward_id ?? 0);

        if ($rewardId <= 0 && is_array($order->loyalty_reward_snapshot)) {
            $rewardId = (int) ($order->loyalty_reward_snapshot['reward_id'] ?? 0);
        }

        if ($rewardId <= 0) {
            return $this->restoreResult(false, 0, null, 'no_reward');
        }

        $redeemKey = $this->redeemIdempotencyKey($orderId, $rewardId);
        $restoreKey = $this->restoreIdempotencyKey($orderId, $rewardId);

        $existingRestore = LoyaltyPointTransaction::query()->where('idempotency_key', $restoreKey)->first();

        if ($existingRestore !== null) {
            return $this->restoreResult(true, abs((int) $existingRestore->points), (int) $existingRestore->getKey(), 'idempotent');
        }

        $redeem = LoyaltyPointTransaction::query()
            ->where('idempotency_key', $redeemKey)
            ->where('type', LoyaltyTransactionType::Redeem->value)
            ->first();

        if ($redeem === null) {
            return $this->restoreResult(false, 0, null, 'no_redeem');
        }

        $points = abs((int) $redeem->points);

        if ($points <= 0) {
            return $this->restoreResult(false, 0, null, 'zero_points');
        }

        try {
            $transaction = DB::transaction(function () use ($redeem, $order, $points, $restoreKey, $reasonCode, $rewardId): LoyaltyPointTransaction {
                $duplicate = LoyaltyPointTransaction::query()
                    ->where('idempotency_key', $restoreKey)
                    ->lockForUpdate()
                    ->first();

                if ($duplicate !== null) {
                    return $duplicate;
                }

                $account = LoyaltyAccount::query()
                    ->whereKey($redeem->loyalty_account_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $transaction = LoyaltyPointTransaction::query()->create([
                    'loyalty_account_id' => $account->getKey(),
                    'customer_id' => $redeem->customer_id,
                    'type' => LoyaltyTransactionType::Reversal,
                    'points' => $points,
                    'source_type' => LoyaltyTransactionSourceType::Order,
                    'source_id' => (int) $order->getKey(),
                    'idempotency_key' => $restoreKey,
                    'reason_code' => $reasonCode ?: 'order_loyalty_restore',
                    'description' => sprintf('Restored points for order %s', $order->order_number ?? $order->getKey()),
                    'metadata' => [
                        'reward_id' => $rewardId,
                        'restored_transaction_id' => (int) $redeem->getKey(),
                        'order_number' => $order->order_number,
                    ],
                    'occurred_at' => now(),
                ]);

                // lifetime_redeemed_points is historical and is not reduced by restores.
                $account->forceFill([
                    'available_points' => (int) $account->available_points + $points,
                    'version' => (int) $account->version + 1,
                ])->save();

                return $transaction;
            });
        } catch (Throwable $exception) {
            $existing = LoyaltyPointTransaction::query()->where('idempotency_key', $restoreKey)->first();

            if ($existing !== null) {
                return $this->restoreResult(true, abs((int) $existing->points), (int) $existing->getKey(), 'idempotent');
            }

            throw $exception;
        }

        return $this->restoreResult(true, $points, (int) $transaction->getKey(), 'restored');
    }

    /**
     * @return array{adjusted: bool, points: int, transaction_id: int|null, reason: string}
     */
    public function adjustPoints(
        User $customer,
        User $actor,
        int $points,
        string $reason,
        ?string $idempotencyKey = null,
    ): array {
        if (! $customer->hasRole(UserRole::Customer)) {
            throw new \InvalidArgumentException('Adjustments are only available for customers.');
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'A reason is required for loyalty adjustments.',
            ]);
        }

        if ($points === 0) {
            throw ValidationException::withMessages([
                'points' => 'Adjustment points must be a non-zero integer.',
            ]);
        }

        $key = $idempotencyKey ?: sprintf(
            'adjustment:admin:%d:customer:%d:%s',
            (int) $actor->getKey(),
            (int) $customer->getKey(),
            hash('sha256', $points.'|'.$reason.'|'.now()->format('YmdHis')),
        );

        $existing = LoyaltyPointTransaction::query()->where('idempotency_key', $key)->first();

        if ($existing !== null) {
            return $this->adjustResult(true, (int) $existing->points, (int) $existing->getKey(), 'idempotent');
        }

        $transaction = DB::transaction(function () use ($customer, $actor, $points, $reason, $key): LoyaltyPointTransaction {
            $duplicate = LoyaltyPointTransaction::query()
                ->where('idempotency_key', $key)
                ->lockForUpdate()
                ->first();

            if ($duplicate !== null) {
                return $duplicate;
            }

            $account = LoyaltyAccount::query()
                ->where('customer_id', $customer->getKey())
                ->lockForUpdate()
                ->first();

            if ($account === null) {
                $this->ensureAccount($customer);
                $account = LoyaltyAccount::query()
                    ->where('customer_id', $customer->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $transaction = LoyaltyPointTransaction::query()->create([
                'loyalty_account_id' => $account->getKey(),
                'customer_id' => $customer->getKey(),
                'type' => LoyaltyTransactionType::Adjustment,
                'points' => $points,
                'source_type' => LoyaltyTransactionSourceType::Admin,
                'source_id' => (int) $actor->getKey(),
                'idempotency_key' => $key,
                'reason_code' => 'admin_adjustment',
                'description' => $reason,
                'metadata' => [
                    'actor_id' => (int) $actor->getKey(),
                    'actor_name' => $actor->name,
                ],
                'occurred_at' => now(),
            ]);

            $account->forceFill([
                'available_points' => (int) $account->available_points + $points,
                'lifetime_adjusted_points' => (int) $account->lifetime_adjusted_points + $points,
                'version' => (int) $account->version + 1,
            ])->save();

            return $transaction;
        });

        return $this->adjustResult(true, (int) $transaction->points, (int) $transaction->getKey(), 'adjusted');
    }

    public function recentTransactions(User $customer, int $limit = 20): array
    {
        return LoyaltyPointTransaction::query()
            ->where('customer_id', $customer->getKey())
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(max(1, min(50, $limit)))
            ->get()
            ->all();
    }

    /**
     * Admin read model for a customer.
     *
     * @return array{
     *     account: LoyaltyAccount|null,
     *     transactions: LengthAwarePaginator
     * }
     */
    public function adminHistoryForCustomer(User $customer, int $perPage = 25): array
    {
        $account = LoyaltyAccount::query()->where('customer_id', $customer->getKey())->first();

        $transactions = LoyaltyPointTransaction::query()
            ->where('customer_id', $customer->getKey())
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return [
            'account' => $account,
            'transactions' => $transactions,
        ];
    }

    public function isEnabled(): bool
    {
        return (bool) config('loyalty.enabled', false);
    }

    public function customerExplanation(): ?string
    {
        $text = trim((string) config('loyalty.customer_explanation', ''));

        return $text !== '' ? $text : null;
    }

    protected function paymentIsConfirmed(Order $order): bool
    {
        if ($order->dining_session_id !== null) {
            $session = $order->diningSession;
            $sessionPayment = $session?->payment_status instanceof PaymentStatus
                ? $session->payment_status
                : PaymentStatus::tryFrom((string) ($session?->payment_status ?? ''));

            return $sessionPayment === PaymentStatus::Confirmed;
        }

        $payment = $order->payment_status instanceof PaymentStatus
            ? $order->payment_status
            : PaymentStatus::tryFrom((string) $order->payment_status);

        return $payment === PaymentStatus::Confirmed;
    }

    protected function isOnOrAfterEffectiveAt(Order $order): bool
    {
        $raw = config('loyalty.effective_at');

        if ($raw === null || $raw === '') {
            return true;
        }

        try {
            $effectiveAt = Carbon::parse((string) $raw);
        } catch (Throwable) {
            return true;
        }

        $anchor = $order->completed_at ?? $order->updated_at ?? now();

        return $anchor->greaterThanOrEqualTo($effectiveAt);
    }

    protected function earnIdempotencyKey(int $orderId): string
    {
        return 'earn:order:'.$orderId;
    }

    protected function reversalIdempotencyKey(int $orderId): string
    {
        return 'reversal:order:'.$orderId;
    }

    protected function redeemIdempotencyKey(int $orderId, int $rewardId): string
    {
        return 'redeem:order:'.$orderId.':reward:'.$rewardId;
    }

    protected function restoreIdempotencyKey(int $orderId, int $rewardId): string
    {
        return 'restore:order:'.$orderId.':reward:'.$rewardId;
    }

    /**
     * @return array{awarded: bool, points: int, transaction_id: int|null, reason: string}
     */
    protected function awardResult(bool $awarded, int $points, ?int $transactionId, string $reason): array
    {
        return [
            'awarded' => $awarded,
            'points' => $points,
            'transaction_id' => $transactionId,
            'reason' => $reason,
        ];
    }

    /**
     * @return array{reversed: bool, points: int, transaction_id: int|null, reason: string}
     */
    protected function reversalResult(bool $reversed, int $points, ?int $transactionId, string $reason): array
    {
        return [
            'reversed' => $reversed,
            'points' => $points,
            'transaction_id' => $transactionId,
            'reason' => $reason,
        ];
    }

    /**
     * @return array{redeemed: bool, points: int, transaction_id: int|null, reason: string}
     */
    protected function redeemResult(bool $redeemed, int $points, ?int $transactionId, string $reason): array
    {
        return [
            'redeemed' => $redeemed,
            'points' => $points,
            'transaction_id' => $transactionId,
            'reason' => $reason,
        ];
    }

    /**
     * @return array{restored: bool, points: int, transaction_id: int|null, reason: string}
     */
    protected function restoreResult(bool $restored, int $points, ?int $transactionId, string $reason): array
    {
        return [
            'restored' => $restored,
            'points' => $points,
            'transaction_id' => $transactionId,
            'reason' => $reason,
        ];
    }

    /**
     * @return array{adjusted: bool, points: int, transaction_id: int|null, reason: string}
     */
    protected function adjustResult(bool $adjusted, int $points, ?int $transactionId, string $reason): array
    {
        return [
            'adjusted' => $adjusted,
            'points' => $points,
            'transaction_id' => $transactionId,
            'reason' => $reason,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function toCustomerTransaction(LoyaltyPointTransaction $txn): array
    {
        $type = $txn->type instanceof LoyaltyTransactionType
            ? $txn->type
            : LoyaltyTransactionType::tryFrom((string) $txn->type);

        return [
            'id' => (int) $txn->getKey(),
            'type' => $type?->value ?? (string) $txn->type,
            'label' => $type?->customerLabel() ?? 'Points update',
            'points' => (int) $txn->points,
            'description' => $txn->description,
            'occurred_at' => $txn->occurred_at?->toIso8601String(),
        ];
    }

    protected function decimalConfig(string $key, string $default): string
    {
        $value = config($key, $default);

        if ($value === null || $value === '') {
            return $default;
        }

        return number_format((float) $value, 4, '.', '');
    }

    protected function bcMaxZero(string $amount): string
    {
        return bccomp($amount, '0', 2) < 0 ? '0.00' : number_format((float) $amount, 2, '.', '');
    }
}
