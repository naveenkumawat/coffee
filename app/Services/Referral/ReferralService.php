<?php

namespace App\Services\Referral;

use App\Enums\CustomerNotificationType;
use App\Enums\CustomerRewardStatus;
use App\Enums\CustomerRewardType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PromotionDiscountType;
use App\Enums\ReferralRewardMode;
use App\Enums\ReferralStatus;
use App\Enums\UserRole;
use App\Models\CustomerReferral;
use App\Models\CustomerReward;
use App\Models\DiningSession;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Notifications\ReferralRewardEarnedNotification;
use App\Services\CafeAvailability\CafeAvailabilityServiceInterface;
use App\Services\Notification\CustomerNotificationDispatcherInterface;
use App\Services\Tax\TaxCalculatorInterface;
use App\Services\WebsiteSetting\WebsiteSettingServiceInterface;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReferralService implements ReferralServiceInterface
{
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(
        protected WebsiteSettingServiceInterface $websiteSettings,
        protected CafeAvailabilityServiceInterface $cafeAvailability,
        protected TaxCalculatorInterface $taxCalculator,
        protected CustomerNotificationDispatcherInterface $notifications,
    ) {}

    public function normalizeCode(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        $normalized = strtoupper(preg_replace('/\s+/', '', trim($code)) ?? '');

        return $normalized === '' ? null : $normalized;
    }

    public function generateUniqueReferralCode(User $user): string
    {
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string) $user->name) ?: 'CC', 0, 3));
        if (strlen($prefix) < 3) {
            $prefix = str_pad($prefix, 3, 'X');
        }

        for ($attempt = 0; $attempt < 40; $attempt++) {
            $suffix = '';
            for ($i = 0; $i < 4; $i++) {
                $suffix .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
            $code = $prefix.$suffix;

            if (! User::query()->where('referral_code', $code)->exists()) {
                return $code;
            }
        }

        return 'CC'.Str::upper(Str::random(6));
    }

    public function ensureCustomerReferralCode(User $user): string
    {
        if ($user->role !== UserRole::Customer) {
            throw ValidationException::withMessages([
                'referral_code' => 'Only customers receive referral codes.',
            ]);
        }

        if (filled($user->referral_code)) {
            return (string) $user->referral_code;
        }

        $code = $this->generateUniqueReferralCode($user);
        $user->forceFill(['referral_code' => $code])->save();

        return $code;
    }

    public function resolveReferrerByCode(?string $code): ?User
    {
        $normalized = $this->normalizeCode($code);

        if ($normalized === null) {
            return null;
        }

        return User::query()
            ->where('role', UserRole::Customer->value)
            ->where('is_active', true)
            ->whereRaw('UPPER(referral_code) = ?', [$normalized])
            ->first();
    }

    public function attachReferralOnRegistration(User $newCustomer, ?string $referralCode): ?CustomerReferral
    {
        $normalized = $this->normalizeCode($referralCode);

        if ($normalized === null) {
            return null;
        }

        if (! (bool) ($this->settings()['enabled'] ?? false)) {
            throw ValidationException::withMessages([
                'referral_code' => 'Referral codes are not available right now.',
            ]);
        }

        $referrer = $this->resolveReferrerByCode($normalized);

        if ($referrer === null) {
            throw ValidationException::withMessages([
                'referral_code' => 'That referral code is not valid.',
            ]);
        }

        if ((int) $referrer->getKey() === (int) $newCustomer->getKey()) {
            throw ValidationException::withMessages([
                'referral_code' => 'You cannot use your own referral code.',
            ]);
        }

        if ($newCustomer->referred_by_user_id !== null || $newCustomer->referralAsReferred()->exists()) {
            throw ValidationException::withMessages([
                'referral_code' => 'A referral has already been applied to this account.',
            ]);
        }

        return DB::transaction(function () use ($newCustomer, $referrer, $normalized): CustomerReferral {
            $newCustomer->forceFill(['referred_by_user_id' => $referrer->getKey()])->save();

            return CustomerReferral::query()->create([
                'referrer_user_id' => $referrer->getKey(),
                'referred_user_id' => $newCustomer->getKey(),
                'referral_code_snapshot' => $normalized,
                'status' => ReferralStatus::Registered,
            ]);
        });
    }

    public function qualifyOrderIfEligible(Order $order): ?CustomerReward
    {
        if (! (bool) ($this->settings()['enabled'] ?? false)) {
            return null;
        }

        // Dining round orders are non-revenue; session payment qualifies instead.
        if ($order->isDiningRound()) {
            return null;
        }

        if ($order->customer_id === null) {
            return null;
        }

        if ($order->payment_status !== PaymentStatus::Confirmed) {
            return null;
        }

        if (in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Rejected], true)) {
            return null;
        }

        $minimum = $this->settings()['minimum_qualifying_order_amount'] ?? null;
        if ($minimum !== null && bccomp((string) $order->total_amount, (string) $minimum, 2) < 0) {
            return null;
        }

        return $this->qualifyCustomerPurchase(
            (int) $order->customer_id,
            $order,
        );
    }

    /**
     * Idempotent: qualify at most once when a paid dining session belongs to an authenticated customer.
     */
    public function qualifyDiningSessionIfEligible(DiningSession $session): ?CustomerReward
    {
        if (! (bool) ($this->settings()['enabled'] ?? false)) {
            return null;
        }

        if ($session->customer_id === null) {
            return null;
        }

        if ($session->payment_status !== PaymentStatus::Confirmed) {
            return null;
        }

        $minimum = $this->settings()['minimum_qualifying_order_amount'] ?? null;
        if ($minimum !== null && bccomp((string) $session->total_amount, (string) $minimum, 2) < 0) {
            return null;
        }

        $anchorOrder = $session->orders()
            ->whereNotIn('status', [OrderStatus::Cancelled->value, OrderStatus::Rejected->value])
            ->orderBy('dining_round_number')
            ->orderBy('id')
            ->first();

        if ($anchorOrder === null) {
            return null;
        }

        return $this->qualifyCustomerPurchase(
            (int) $session->customer_id,
            $anchorOrder,
        );
    }

    protected function qualifyCustomerPurchase(
        int $customerId,
        Order $anchorOrder,
    ): ?CustomerReward {
        return DB::transaction(function () use ($customerId, $anchorOrder): ?CustomerReward {
            /** @var CustomerReferral|null $referral */
            $referral = CustomerReferral::query()
                ->where('referred_user_id', $customerId)
                ->lockForUpdate()
                ->first();

            if ($referral === null) {
                return null;
            }

            if ($referral->status === ReferralStatus::Rewarded) {
                return $referral->reward;
            }

            if ($referral->status === ReferralStatus::Cancelled) {
                return null;
            }

            $existingReward = CustomerReward::query()
                ->where('source_referral_id', $referral->getKey())
                ->lockForUpdate()
                ->first();

            if ($existingReward !== null) {
                if ($referral->status !== ReferralStatus::Rewarded) {
                    $referral->forceFill([
                        'status' => ReferralStatus::Rewarded,
                        'qualified_order_id' => $referral->qualified_order_id ?: $anchorOrder->getKey(),
                        'qualified_at' => $referral->qualified_at ?: now(),
                    ])->save();
                }

                return $existingReward;
            }

            $maxPerMonth = $this->settings()['max_rewards_per_customer_month'] ?? null;
            if ($maxPerMonth !== null) {
                $monthStart = $this->cafeAvailability->now()->startOfMonth();
                $count = CustomerReward::query()
                    ->where('user_id', $referral->referrer_user_id)
                    ->where('source_type', 'referral')
                    ->where('earned_at', '>=', $monthStart)
                    ->count();

                if ($count >= $maxPerMonth) {
                    return null;
                }
            }

            $reward = $this->createRewardFromSettings($referral, $anchorOrder);

            $referral->forceFill([
                'status' => ReferralStatus::Rewarded,
                'qualified_order_id' => $anchorOrder->getKey(),
                'qualified_at' => now(),
            ])->save();

            $this->notifyReferrer($reward);

            return $reward;
        });
    }

    public function settings(): array
    {
        $config = $this->websiteSettings->referralConfig();

        return [
            ...$config,
            'customer_message' => 'Refer a friend. When they place their first qualifying order, you receive a free drink or reward coupon.',
        ];
    }

    public function shareUrl(User $customer): string
    {
        $code = $this->ensureCustomerReferralCode($customer);
        $base = rtrim((string) config('coffee.pwa.url'), '/');

        return $base.'/register?ref='.urlencode($code);
    }

    public function activeRewardsFor(User $customer): Collection
    {
        $at = $this->cafeAvailability->now();

        return CustomerReward::query()
            ->activeForCustomer((int) $customer->getKey(), $at)
            ->orderBy('expires_at')
            ->orderBy('id')
            ->get();
    }

    public function customerStats(User $customer): array
    {
        $at = $this->cafeAvailability->now();

        return [
            'successful_referrals' => CustomerReferral::query()
                ->where('referrer_user_id', $customer->getKey())
                ->where('status', ReferralStatus::Rewarded->value)
                ->count(),
            'available_rewards' => CustomerReward::query()
                ->activeForCustomer((int) $customer->getKey(), $at)
                ->count(),
            'redeemed_rewards' => CustomerReward::query()
                ->where('user_id', $customer->getKey())
                ->where('status', CustomerRewardStatus::Redeemed->value)
                ->count(),
            'expired_rewards' => CustomerReward::query()
                ->where('user_id', $customer->getKey())
                ->where(function ($query) use ($at): void {
                    $query->where('status', CustomerRewardStatus::Expired->value)
                        ->orWhere(function ($inner) use ($at): void {
                            $inner->where('status', CustomerRewardStatus::Available->value)
                                ->whereNotNull('expires_at')
                                ->where('expires_at', '<=', $at);
                        });
                })
                ->count(),
        ];
    }

    public function findOwnedUsableReward(User $customer, int $rewardId): CustomerReward
    {
        /** @var CustomerReward|null $reward */
        $reward = CustomerReward::query()
            ->whereKey($rewardId)
            ->where('user_id', $customer->getKey())
            ->first();

        if ($reward === null) {
            throw ValidationException::withMessages([
                'reward_id' => 'That reward is not available.',
            ]);
        }

        $this->assertRewardUsable($reward);

        return $reward;
    }

    public function findOwnedUsableCouponReward(User $customer, string $code): CustomerReward
    {
        $normalized = $this->normalizeCode($code);

        if ($normalized === null) {
            throw ValidationException::withMessages([
                'referral_coupon' => 'Enter a referral reward code.',
            ]);
        }

        /** @var CustomerReward|null $reward */
        $reward = CustomerReward::query()
            ->where('user_id', $customer->getKey())
            ->where('reward_type', CustomerRewardType::Coupon->value)
            ->whereRaw('UPPER(coupon_code) = ?', [$normalized])
            ->first();

        if ($reward === null) {
            throw ValidationException::withMessages([
                'referral_coupon' => 'That referral reward code is not valid.',
            ]);
        }

        $this->assertRewardUsable($reward);

        return $reward;
    }

    public function resolveFreeDrinkBenefit(CustomerReward $reward, array $items): ?array
    {
        if ($reward->reward_type !== CustomerRewardType::FreeDrink) {
            return null;
        }

        $neededQty = max(1, (int) ($reward->quantity ?: 1));
        $matchedGross = '0.00';
        $matchedQty = 0;
        $productId = $reward->product_id !== null ? (int) $reward->product_id : null;
        $variantId = $reward->variant_id !== null ? (int) $reward->variant_id : null;

        foreach ($items as $item) {
            $itemProductId = isset($item['product_id']) ? (int) $item['product_id'] : null;
            $itemVariantId = isset($item['product_variant_id'])
                ? (int) $item['product_variant_id']
                : (isset($item['variant_id']) ? (int) $item['variant_id'] : null);

            if ($productId !== null && $itemProductId !== $productId) {
                continue;
            }

            if ($variantId !== null && $itemVariantId !== $variantId) {
                continue;
            }

            $lineQty = (int) ($item['quantity'] ?? 0);
            $lineSubtotal = $this->normalizeMoney((string) ($item['line_subtotal'] ?? '0'));
            if ($lineQty <= 0 || bccomp($lineSubtotal, '0', 2) <= 0) {
                continue;
            }

            $unit = bcdiv($lineSubtotal, (string) $lineQty, 4);
            $take = min($neededQty - $matchedQty, $lineQty);
            if ($take <= 0) {
                break;
            }

            $matchedGross = bcadd($matchedGross, bcmul($unit, (string) $take, 4), 4);
            $matchedQty += $take;

            if ($matchedQty >= $neededQty) {
                break;
            }
        }

        if ($matchedQty < $neededQty) {
            return null;
        }

        $original = $this->normalizeMoney($matchedGross);
        $taxConfig = $this->taxCalculator->currentConfig();
        $benefit = $original;

        if ($taxConfig['inclusive'] && $taxConfig['enabled']) {
            $taxComponent = $this->taxCalculator->extractInclusiveTaxComponent($original);
            $benefit = bcsub($original, $taxComponent, 2);
            if (bccomp($benefit, '0', 2) < 0) {
                $benefit = '0.00';
            }
        }

        return [
            'benefit' => $benefit,
            'original_amount' => $original,
            'preserved_taxable' => $original,
            'matched' => true,
            'product_id' => $productId,
            'variant_id' => $variantId,
            'quantity' => $neededQty,
        ];
    }

    public function resolveCouponBenefit(CustomerReward $reward, string $merchandiseSubtotalAfterFreeDrink): string
    {
        if ($reward->reward_type !== CustomerRewardType::Coupon) {
            return '0.00';
        }

        $eligible = $this->normalizeMoney($merchandiseSubtotalAfterFreeDrink);

        if (
            $reward->minimum_subtotal !== null
            && bccomp($eligible, (string) $reward->minimum_subtotal, 2) < 0
        ) {
            return '0.00';
        }

        if ($reward->discount_type === PromotionDiscountType::Percentage) {
            $raw = bcdiv(bcmul($eligible, (string) $reward->discount_value, 6), '100', 6);
            $amount = $this->normalizeMoney($raw);
            if ($reward->maximum_discount_amount !== null && bccomp($amount, (string) $reward->maximum_discount_amount, 2) > 0) {
                $amount = $this->normalizeMoney((string) $reward->maximum_discount_amount);
            }
        } else {
            $amount = $this->normalizeMoney((string) $reward->discount_value);
        }

        if (bccomp($amount, $eligible, 2) > 0) {
            $amount = $eligible;
        }

        return bccomp($amount, '0', 2) < 0 ? '0.00' : $amount;
    }

    public function assertRewardUsable(CustomerReward $reward, ?CarbonInterface $at = null): void
    {
        $at = $at ? CarbonImmutable::parse($at) : $this->cafeAvailability->now();

        if ($reward->status === CustomerRewardStatus::Redeemed) {
            throw ValidationException::withMessages([
                'reward_id' => 'This reward has already been redeemed.',
            ]);
        }

        if ($reward->status === CustomerRewardStatus::Cancelled) {
            throw ValidationException::withMessages([
                'reward_id' => 'This reward is no longer available.',
            ]);
        }

        if ($reward->isExpiredAt($at) || $reward->status === CustomerRewardStatus::Expired) {
            if ($reward->status === CustomerRewardStatus::Available) {
                $reward->forceFill(['status' => CustomerRewardStatus::Expired])->save();
            }

            throw ValidationException::withMessages([
                'reward_id' => 'This reward has expired.',
            ]);
        }

        if ($reward->status !== CustomerRewardStatus::Available && $reward->status !== CustomerRewardStatus::Reserved) {
            throw ValidationException::withMessages([
                'reward_id' => 'This reward is not available.',
            ]);
        }
    }

    protected function createRewardFromSettings(CustomerReferral $referral, Order $order): CustomerReward
    {
        $settings = $this->settings();
        $earnedAt = $this->cafeAvailability->now();
        $expiresAt = $earnedAt->addDays((int) $settings['reward_redemption_duration_days']);
        $mode = ReferralRewardMode::from($settings['reward_type']);

        if ($mode === ReferralRewardMode::FreeDrink) {
            $productId = $settings['reward_product_id'];
            $variantId = $settings['reward_variant_id'];

            if ($productId === null) {
                throw ValidationException::withMessages([
                    'referral' => 'Referral free-drink reward is not configured.',
                ]);
            }

            $product = Product::query()->find($productId);
            $variant = $variantId !== null ? ProductVariant::query()->find($variantId) : null;

            if ($product === null || ! $product->is_active) {
                throw ValidationException::withMessages([
                    'referral' => 'Referral free-drink reward product is invalid.',
                ]);
            }

            try {
                return CustomerReward::query()->create([
                    'user_id' => $referral->referrer_user_id,
                    'source_type' => 'referral',
                    'source_referral_id' => $referral->getKey(),
                    'reward_type' => CustomerRewardType::FreeDrink,
                    'status' => CustomerRewardStatus::Available,
                    'earned_at' => $earnedAt,
                    'expires_at' => $expiresAt,
                    'product_id' => $product->getKey(),
                    'variant_id' => $variant?->getKey(),
                    'product_name_snapshot' => $product->name,
                    'variant_name_snapshot' => $variant?->name,
                    'quantity' => (int) $settings['reward_quantity'],
                ]);
            } catch (UniqueConstraintViolationException) {
                return CustomerReward::query()->where('source_referral_id', $referral->getKey())->firstOrFail();
            }
        }

        $couponCode = $this->generateUniqueCouponCode();

        try {
            return CustomerReward::query()->create([
                'user_id' => $referral->referrer_user_id,
                'source_type' => 'referral',
                'source_referral_id' => $referral->getKey(),
                'reward_type' => CustomerRewardType::Coupon,
                'status' => CustomerRewardStatus::Available,
                'earned_at' => $earnedAt,
                'expires_at' => $expiresAt,
                'coupon_code' => $couponCode,
                'discount_type' => $settings['coupon_discount_type'],
                'discount_value' => $settings['coupon_discount_value'],
                'maximum_discount_amount' => $settings['coupon_max_discount'],
                'minimum_subtotal' => $settings['coupon_minimum_subtotal'],
            ]);
        } catch (UniqueConstraintViolationException) {
            return CustomerReward::query()->where('source_referral_id', $referral->getKey())->firstOrFail();
        }
    }

    protected function generateUniqueCouponCode(): string
    {
        for ($attempt = 0; $attempt < 40; $attempt++) {
            $suffix = '';
            for ($i = 0; $i < 6; $i++) {
                $suffix .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
            $code = 'REF-'.$suffix;

            if (! CustomerReward::query()->where('coupon_code', $code)->exists()) {
                return $code;
            }
        }

        return 'REF-'.Str::upper(Str::random(8));
    }

    protected function notifyReferrer(CustomerReward $reward): void
    {
        $referrer = $reward->user;

        if ($referrer === null) {
            return;
        }

        $this->notifications->sendOnce(
            CustomerNotificationType::ReferralRewardEarned,
            'referral_reward:'.$reward->getKey(),
            (string) $referrer->email,
            new ReferralRewardEarnedNotification($reward),
            $referrer,
            null,
            'Referral reward earned',
        );
    }

    protected function normalizeMoney(string $value): string
    {
        if (! is_numeric($value)) {
            return '0.00';
        }

        $negative = bccomp($value, '0', 8) < 0;
        $absolute = $negative ? bcmul($value, '-1', 8) : $value;
        $scaled = bcmul($absolute, '100', 4);
        $rounded = bcadd($scaled, '0.5', 0);
        $result = bcdiv($rounded, '100', 2);

        return $negative ? bcmul($result, '-1', 2) : $result;
    }
}
