<?php

namespace App\Services\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;
use App\Transfers\Cart\CartItemTransferInterface;

interface CartServiceInterface
{
    public function getForCustomer(User $customer): Cart;

    public function addItem(User $customer, CartItemTransferInterface $data): Cart;

    public function updateItem(User $customer, CartItem $cartItem, CartItemTransferInterface $data): Cart;

    public function removeItem(User $customer, CartItem $cartItem): Cart;

    public function clear(User $customer): Cart;

    /**
     * @param  list<array{product_variant_id: int, quantity: int}>  $items
     */
    public function mergeGuestItems(User $customer, array $items, ?string $idempotencyKey = null): Cart;

    public function count(User $customer): int;

    /**
     * @return array{
     *     item_count: int,
     *     subtotal: string,
     *     discount_total: string,
     *     discounts: list<array{promotion_id: int, name: string, code: ?string, discount_type: string, discount_value: string, amount: string}>,
     *     promo_code: ?string,
     *     promo_error: ?string,
     *     free_drink_benefit: string,
     *     referral_coupon_discount: string,
     *     referral_rewards: list<array<string, mixed>>,
     *     reward_error: ?string,
     *     total: string,
     *     tax: array<string, mixed>,
     *     has_unavailable_items: bool
     * }
     */
    public function summarize(Cart $cart, ?string $fulfilmentMethod = null): array;

    public function applyPromoCode(User $customer, string $code, ?string $fulfilmentMethod = null): Cart;

    public function clearPromoCode(User $customer): Cart;

    public function addFreeDrinkRewardToCart(User $customer, int $rewardId, ?string $fulfilmentMethod = null): Cart;

    public function applyReferralCouponReward(User $customer, string $code, ?string $fulfilmentMethod = null): Cart;

    public function clearReferralRewards(User $customer): Cart;
}
