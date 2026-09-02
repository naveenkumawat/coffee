<?php

namespace App\Services\Referral;

use App\Models\CustomerReferral;
use App\Models\CustomerReward;
use App\Models\DiningSession;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

interface ReferralServiceInterface
{
    public function normalizeCode(?string $code): ?string;

    public function generateUniqueReferralCode(User $user): string;

    public function ensureCustomerReferralCode(User $user): string;

    public function resolveReferrerByCode(?string $code): ?User;

    /**
     * @throws ValidationException
     */
    public function attachReferralOnRegistration(User $newCustomer, ?string $referralCode): ?CustomerReferral;

    /**
     * Idempotent: create at most one reward when referred customer's order qualifies.
     */
    public function qualifyOrderIfEligible(Order $order): ?CustomerReward;

    /**
     * Idempotent: create at most one reward when a paid dining session qualifies.
     */
    public function qualifyDiningSessionIfEligible(DiningSession $session): ?CustomerReward;

    /**
     * @return array<string, mixed>
     */
    public function settings(): array;

    public function shareUrl(User $customer): string;

    /**
     * @return Collection<int, CustomerReward>
     */
    public function activeRewardsFor(User $customer): Collection;

    /**
     * @return array{successful_referrals: int, available_rewards: int, redeemed_rewards: int, expired_rewards: int}
     */
    public function customerStats(User $customer): array;

    public function findOwnedUsableReward(User $customer, int $rewardId): CustomerReward;

    public function findOwnedUsableCouponReward(User $customer, string $code): CustomerReward;

    /**
     * Free-drink benefit amount that does NOT reduce the GST basis.
     *
     * @param  list<array{product_id: ?int, product_variant_id?: ?int, variant_id?: ?int, line_subtotal: string, quantity: int}>  $items
     * @return array{benefit: string, original_amount: string, preserved_taxable: string, matched: bool, product_id: ?int, variant_id: ?int, quantity: int}|null
     */
    public function resolveFreeDrinkBenefit(CustomerReward $reward, array $items): ?array;

    /**
     * Coupon benefit that DOES reduce GST basis (like a personal promo).
     */
    public function resolveCouponBenefit(CustomerReward $reward, string $merchandiseSubtotalAfterFreeDrink): string;

    /**
     * @throws ValidationException
     */
    public function assertRewardUsable(CustomerReward $reward, ?CarbonInterface $at = null): void;
}
