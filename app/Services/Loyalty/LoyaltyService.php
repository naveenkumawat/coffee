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
                'version' => 1,
            ],
        );
    }

    public function customerPayload(User $customer, int $historyLimit = 20): array
    {
        $account = $this->ensureAccount($customer);
        $limit = max(1, min(50, $historyLimit));

        return [
            'available_points' => (int) $account->available_points,
            'lifetime_earned_points' => (int) $account->lifetime_earned_points,
            'lifetime_redeemed_points' => (int) $account->lifetime_redeemed_points,
            'earning_enabled' => $this->isEnabled(),
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

                // P3.1 / P3.2 invariant: never silently clamp. Without redemption,
                // available should cover the earn; reject if it would go negative.
                if ((int) $account->available_points < $points) {
                    throw new \RuntimeException('loyalty.reversal_would_go_negative');
                }

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
                    ],
                    'occurred_at' => now(),
                ]);

                $account->forceFill([
                    'available_points' => (int) $account->available_points - $points,
                    'lifetime_earned_points' => max(0, (int) $account->lifetime_earned_points - $points),
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

            if ($exception->getMessage() === 'loyalty.reversal_would_go_negative') {
                Log::warning('loyalty.reversal_rejected_negative', [
                    'order_id' => $orderId,
                ]);

                return $this->reversalResult(false, 0, null, 'would_go_negative');
            }

            throw $exception;
        }

        return $this->reversalResult(true, $points, (int) $transaction->getKey(), 'reversed');
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
