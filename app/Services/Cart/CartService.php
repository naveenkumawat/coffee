<?php

namespace App\Services\Cart;

use App\Enums\CustomerRewardType;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CustomerReward;
use App\Models\ProductVariant;
use App\Models\User;
use App\Repositories\Cart\CartRepositoryInterface;
use App\Services\Promotion\PromotionServiceInterface;
use App\Services\Referral\ReferralServiceInterface;
use App\Services\Tax\TaxCalculatorInterface;
use App\Transfers\Cart\CartItemTransfer;
use App\Transfers\Cart\CartItemTransferInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartService implements CartServiceInterface
{
    public function __construct(
        protected CartRepositoryInterface $carts,
        protected TaxCalculatorInterface $taxCalculator,
        protected PromotionServiceInterface $promotions,
        protected ReferralServiceInterface $referrals,
    ) {}

    public function getForCustomer(User $customer): Cart
    {
        return $this->carts->firstOrCreateForCustomer($customer);
    }

    public function addItem(User $customer, CartItemTransferInterface $data): Cart
    {
        return DB::transaction(function () use ($customer, $data): Cart {
            $cart = $this->carts->firstOrCreateForCustomer($customer);
            $variant = $this->validateVariant($data->getProductVariantId());
            $existingItem = $this->carts->findCustomerItem($cart, (int) $variant->getKey());

            if ($existingItem) {
                $this->carts->updateItem($existingItem, [
                    'quantity' => $existingItem->quantity + $data->getQuantity(),
                ]);
            } else {
                $this->carts->createItem($cart, [
                    'product_variant_id' => $variant->getKey(),
                    'quantity' => $data->getQuantity(),
                ]);
            }

            return $this->carts->refreshCart($cart);
        });
    }

    public function updateItem(User $customer, CartItem $cartItem, CartItemTransferInterface $data): Cart
    {
        return DB::transaction(function () use ($customer, $cartItem, $data): Cart {
            $cart = $this->carts->firstOrCreateForCustomer($customer);

            if ((int) $cartItem->cart_id !== (int) $cart->getKey()) {
                throw ValidationException::withMessages([
                    'item' => 'The selected cart item does not belong to the authenticated customer.',
                ]);
            }

            $this->validateVariant((int) $cartItem->product_variant_id);

            $this->carts->updateItem($cartItem, [
                'quantity' => $data->getQuantity(),
            ]);

            return $this->carts->refreshCart($cart);
        });
    }

    public function removeItem(User $customer, CartItem $cartItem): Cart
    {
        return DB::transaction(function () use ($customer, $cartItem): Cart {
            $cart = $this->carts->firstOrCreateForCustomer($customer);

            if ((int) $cartItem->cart_id !== (int) $cart->getKey()) {
                throw ValidationException::withMessages([
                    'item' => 'The selected cart item does not belong to the authenticated customer.',
                ]);
            }

            $this->carts->deleteItem($cartItem);

            return $this->carts->refreshCart($cart);
        });
    }

    public function clear(User $customer): Cart
    {
        return DB::transaction(function () use ($customer): Cart {
            $cart = $this->carts->firstOrCreateForCustomer($customer);
            $this->carts->clearItems($cart);
            $cart->forceFill([
                'promo_code' => null,
                'referral_free_drink_reward_id' => null,
                'referral_coupon_reward_id' => null,
            ])->save();

            return $this->carts->refreshCart($cart);
        });
    }

    public function mergeGuestItems(User $customer, array $items, ?string $idempotencyKey = null): Cart
    {
        $cacheKey = null;

        if (filled($idempotencyKey)) {
            $cacheKey = sprintf('cart-merge:%d:%s', (int) $customer->getKey(), $idempotencyKey);

            if (Cache::has($cacheKey)) {
                return $this->getForCustomer($customer);
            }
        }

        $quantitiesByVariant = [];

        foreach ($items as $item) {
            $variantId = (int) $item['product_variant_id'];
            $quantitiesByVariant[$variantId] = ($quantitiesByVariant[$variantId] ?? 0) + (int) $item['quantity'];
        }

        $cart = DB::transaction(function () use ($customer, $quantitiesByVariant): Cart {
            foreach ($quantitiesByVariant as $variantId => $quantity) {
                $transfer = new CartItemTransfer;
                $transfer->setProductVariantId($variantId);
                $transfer->setQuantity($quantity);
                $this->addItem($customer, $transfer);
            }

            return $this->getForCustomer($customer);
        });

        if ($cacheKey !== null) {
            Cache::put($cacheKey, true, now()->addHour());
        }

        return $cart;
    }

    public function count(User $customer): int
    {
        $cart = $this->carts->findForCustomer($customer);

        if (! $cart) {
            return 0;
        }

        return $this->carts->countItems($cart);
    }

    public function summarize(Cart $cart, ?string $fulfilmentMethod = null): array
    {
        $rewardError = $this->clearExpiredRewardsFromCart($cart);

        $subtotal = '0.00';
        $itemCount = 0;
        $hasUnavailableItems = false;
        $pricedItems = [];

        foreach ($cart->items as $item) {
            $itemCount += (int) $item->quantity;

            if (! $this->isVariantAvailable($item->productVariant)) {
                $hasUnavailableItems = true;

                continue;
            }

            $unitPrice = $this->normalizeMoney((string) $item->productVariant->price);
            $lineSubtotal = bcmul($unitPrice, (string) $item->quantity, 2);
            $subtotal = bcadd($subtotal, $lineSubtotal, 2);
            $product = $item->productVariant->product;

            $pricedItems[] = [
                'product_id' => $product?->getKey() !== null ? (int) $product->getKey() : null,
                'product_variant_id' => (int) $item->product_variant_id,
                'product_category_id' => $product?->product_category_id !== null ? (int) $product->product_category_id : null,
                'quantity' => (int) $item->quantity,
                'unit_price' => $unitPrice,
                'line_subtotal' => $lineSubtotal,
            ];
        }

        $freeDrinkBenefit = '0.00';
        $freeDrinkOriginal = '0.00';
        $referralCouponDiscount = '0.00';
        $referralRewards = [];
        $itemsForPromotions = $pricedItems;

        $freeDrinkReward = $cart->referral_free_drink_reward_id
            ? CustomerReward::query()->find($cart->referral_free_drink_reward_id)
            : null;

        if ($cart->referral_free_drink_reward_id !== null && $freeDrinkReward === null) {
            $cart->forceFill(['referral_free_drink_reward_id' => null])->save();
            $rewardError = $rewardError ?? 'reward_unavailable';
        } elseif ($freeDrinkReward !== null) {
            try {
                $this->referrals->assertRewardUsable($freeDrinkReward);
                $resolved = $this->referrals->resolveFreeDrinkBenefit($freeDrinkReward, $pricedItems);

                if ($resolved === null) {
                    $rewardError = 'reward_item_missing';
                } else {
                    $freeDrinkBenefit = $resolved['benefit'];
                    $freeDrinkOriginal = $resolved['original_amount'];
                    $itemsForPromotions = $this->reduceItemsByFreeDrink($pricedItems, $resolved);
                    $referralRewards[] = [
                        'reward_id' => (int) $freeDrinkReward->getKey(),
                        'reward_type' => CustomerRewardType::FreeDrink->value,
                        'title' => $freeDrinkReward->displayTitle(),
                        'benefit_amount' => $freeDrinkBenefit,
                        'original_amount' => $freeDrinkOriginal,
                        'preserves_gst_basis' => true,
                    ];
                }
            } catch (ValidationException $exception) {
                $messages = $exception->errors();
                $message = $messages['reward_id'][0] ?? 'This reward is not available.';
                $rewardError = str_contains(strtolower($message), 'expired') ? 'reward_expired' : 'reward_unavailable';
                $cart->forceFill(['referral_free_drink_reward_id' => null])->save();
            }
        }

        $couponReward = $cart->referral_coupon_reward_id
            ? CustomerReward::query()->find($cart->referral_coupon_reward_id)
            : null;

        if ($cart->referral_coupon_reward_id !== null && $couponReward === null) {
            $cart->forceFill(['referral_coupon_reward_id' => null])->save();
            $rewardError = $rewardError ?? 'reward_unavailable';
        } elseif ($couponReward !== null) {
            try {
                $this->referrals->assertRewardUsable($couponReward);
                $afterFreeDrink = bcsub($subtotal, $freeDrinkOriginal, 2);
                if (bccomp($afterFreeDrink, '0', 2) < 0) {
                    $afterFreeDrink = '0.00';
                }
                $referralCouponDiscount = $this->referrals->resolveCouponBenefit($couponReward, $afterFreeDrink);

                if (bccomp($referralCouponDiscount, '0', 2) <= 0) {
                    $rewardError = $rewardError ?? 'reward_minimum_not_met';
                } else {
                    $referralRewards[] = [
                        'reward_id' => (int) $couponReward->getKey(),
                        'reward_type' => CustomerRewardType::Coupon->value,
                        'title' => $couponReward->displayTitle(),
                        'code' => $couponReward->coupon_code,
                        'benefit_amount' => $referralCouponDiscount,
                        'preserves_gst_basis' => false,
                    ];
                }
            } catch (ValidationException $exception) {
                $messages = $exception->errors();
                $message = $messages['reward_id'][0] ?? 'This reward is not available.';
                $rewardError = str_contains(strtolower($message), 'expired') ? 'reward_expired' : 'reward_unavailable';
                $cart->forceFill(['referral_coupon_reward_id' => null])->save();
            }
        }

        $promotionResult = $this->promotions->evaluate([
            'customer' => $cart->customer,
            'fulfilment' => $fulfilmentMethod,
            'promo_code' => $cart->promo_code,
            'items' => $itemsForPromotions,
        ]);

        $promoDiscount = $promotionResult['discount_total'];
        // discount_total = promo + referral coupon (free drink benefit is separate)
        $discountTotal = bcadd($promoDiscount, $referralCouponDiscount, 2);

        // GST basis = merchandise after normal promotions and referral coupon (NOT reduced by free drink)
        $gstBasis = bcsub($subtotal, $discountTotal, 2);
        if (bccomp($gstBasis, '0', 2) < 0) {
            $gstBasis = '0.00';
        }

        $payable = bcsub($gstBasis, $freeDrinkBenefit, 2);
        if (bccomp($payable, '0', 2) < 0) {
            $payable = '0.00';
        }

        $tax = $this->taxCalculator->calculateForPayableAndGstBasis($payable, $gstBasis);

        return [
            'item_count' => $itemCount,
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'discounts' => $promotionResult['discounts'],
            'promo_code' => $cart->promo_code,
            'promo_error' => $promotionResult['promo_error'],
            'free_drink_benefit' => $freeDrinkBenefit,
            'referral_coupon_discount' => $referralCouponDiscount,
            'referral_rewards' => $referralRewards,
            'reward_error' => $rewardError,
            'total' => $tax->cafeTotal,
            'tax' => $tax->toCustomerArray(),
            'has_unavailable_items' => $hasUnavailableItems,
        ];
    }

    public function applyPromoCode(User $customer, string $code, ?string $fulfilmentMethod = null): Cart
    {
        $normalized = $this->promotions->normalizeCode($code);

        if ($normalized === null) {
            throw ValidationException::withMessages([
                'promo_code' => 'Enter a promo code.',
            ]);
        }

        $cart = $this->getForCustomer($customer);
        $cart->forceFill(['promo_code' => $normalized])->save();
        $cart = $this->carts->refreshCart($cart);

        $summary = $this->summarize($cart, $fulfilmentMethod);

        if (($summary['promo_error'] ?? null) !== null) {
            $cart->forceFill(['promo_code' => null])->save();

            throw ValidationException::withMessages([
                'promo_code' => $summary['promo_error'],
            ]);
        }

        return $this->carts->refreshCart($cart);
    }

    public function clearPromoCode(User $customer): Cart
    {
        $cart = $this->getForCustomer($customer);
        $cart->forceFill(['promo_code' => null])->save();

        return $this->carts->refreshCart($cart);
    }

    public function addFreeDrinkRewardToCart(User $customer, int $rewardId, ?string $fulfilmentMethod = null): Cart
    {
        $reward = $this->referrals->findOwnedUsableReward($customer, $rewardId);

        if ($reward->reward_type !== CustomerRewardType::FreeDrink) {
            throw ValidationException::withMessages([
                'reward_id' => 'That reward is not a free drink.',
            ]);
        }

        $cart = $this->getForCustomer($customer);
        $cart->forceFill([
            'referral_free_drink_reward_id' => $reward->getKey(),
            'referral_coupon_reward_id' => null,
        ])->save();
        $cart = $this->carts->refreshCart($cart);

        $summary = $this->summarize($cart, $fulfilmentMethod);

        if (($summary['reward_error'] ?? null) === 'reward_item_missing') {
            $cart->forceFill(['referral_free_drink_reward_id' => null])->save();

            throw ValidationException::withMessages([
                'reward_id' => 'Add the free drink item to your cart before applying this reward.',
            ]);
        }

        if (($summary['reward_error'] ?? null) !== null) {
            $cart->forceFill(['referral_free_drink_reward_id' => null])->save();

            throw ValidationException::withMessages([
                'reward_id' => 'This reward cannot be applied right now.',
            ]);
        }

        return $this->carts->refreshCart($cart);
    }

    public function applyReferralCouponReward(User $customer, string $code, ?string $fulfilmentMethod = null): Cart
    {
        $reward = $this->referrals->findOwnedUsableCouponReward($customer, $code);

        $cart = $this->getForCustomer($customer);
        $cart->forceFill([
            'referral_coupon_reward_id' => $reward->getKey(),
            'referral_free_drink_reward_id' => null,
        ])->save();
        $cart = $this->carts->refreshCart($cart);

        $summary = $this->summarize($cart, $fulfilmentMethod);

        if (($summary['reward_error'] ?? null) === 'reward_minimum_not_met') {
            $cart->forceFill(['referral_coupon_reward_id' => null])->save();

            throw ValidationException::withMessages([
                'referral_coupon' => 'Your cart does not meet the minimum for this reward.',
            ]);
        }

        if (($summary['reward_error'] ?? null) !== null || bccomp((string) ($summary['referral_coupon_discount'] ?? '0'), '0', 2) <= 0) {
            $cart->forceFill(['referral_coupon_reward_id' => null])->save();

            throw ValidationException::withMessages([
                'referral_coupon' => 'This referral reward cannot be applied right now.',
            ]);
        }

        return $this->carts->refreshCart($cart);
    }

    public function clearReferralRewards(User $customer): Cart
    {
        $cart = $this->getForCustomer($customer);
        $cart->forceFill([
            'referral_free_drink_reward_id' => null,
            'referral_coupon_reward_id' => null,
        ])->save();

        return $this->carts->refreshCart($cart);
    }

    protected function clearExpiredRewardsFromCart(Cart $cart): ?string
    {
        $changed = false;
        $error = null;

        foreach (['referral_free_drink_reward_id', 'referral_coupon_reward_id'] as $field) {
            $rewardId = $cart->{$field};
            if ($rewardId === null) {
                continue;
            }

            $reward = CustomerReward::query()->find($rewardId);
            if ($reward === null) {
                $cart->{$field} = null;
                $changed = true;
                $error = $error ?? 'reward_unavailable';

                continue;
            }

            try {
                $this->referrals->assertRewardUsable($reward);
            } catch (ValidationException $exception) {
                $messages = $exception->errors();
                $message = $messages['reward_id'][0] ?? 'This reward is not available.';
                $error = str_contains(strtolower($message), 'expired') ? 'reward_expired' : 'reward_unavailable';
                $cart->{$field} = null;
                $changed = true;
            }
        }

        if ($changed) {
            $cart->save();
        }

        return $error;
    }

    /**
     * @param  list<array{product_id: ?int, product_variant_id?: int, product_category_id?: ?int, quantity: int, unit_price: string, line_subtotal: string}>  $items
     * @param  array{benefit: string, original_amount: string, product_id: ?int, variant_id: ?int, quantity: int}  $freeDrink
     * @return list<array{product_id: ?int, product_variant_id?: int, product_category_id?: ?int, quantity: int, unit_price: string, line_subtotal: string}>
     */
    protected function reduceItemsByFreeDrink(array $items, array $freeDrink): array
    {
        $remaining = $freeDrink['original_amount'];
        $productId = $freeDrink['product_id'];
        $variantId = $freeDrink['variant_id'];
        $adjusted = [];

        foreach ($items as $item) {
            $itemProductId = isset($item['product_id']) ? (int) $item['product_id'] : null;
            $itemVariantId = isset($item['product_variant_id']) ? (int) $item['product_variant_id'] : null;
            $lineSubtotal = (string) $item['line_subtotal'];

            $matches = ($productId === null || $itemProductId === $productId)
                && ($variantId === null || $itemVariantId === $variantId);

            if ($matches && bccomp($remaining, '0', 2) > 0) {
                $take = bccomp($lineSubtotal, $remaining, 2) <= 0 ? $lineSubtotal : $remaining;
                $lineSubtotal = bcsub($lineSubtotal, $take, 2);
                $remaining = bcsub($remaining, $take, 2);
            }

            if (bccomp($lineSubtotal, '0', 2) <= 0) {
                continue;
            }

            $adjusted[] = [
                ...$item,
                'line_subtotal' => $lineSubtotal,
            ];
        }

        return $adjusted;
    }

    protected function validateVariant(?int $productVariantId): ProductVariant
    {
        $variant = $productVariantId ? $this->carts->findPurchasableVariant($productVariantId) : null;

        if (! $this->isVariantAvailable($variant)) {
            throw ValidationException::withMessages([
                'product_variant_id' => 'Only active and available product variants can be added to the cart.',
            ]);
        }

        return $variant;
    }

    protected function isVariantAvailable(?ProductVariant $variant): bool
    {
        return $variant instanceof ProductVariant
            && $variant->is_active
            && $variant->is_available
            && $variant->product !== null
            && $variant->product->is_active
            && $variant->product->is_available;
    }

    protected function normalizeMoney(string $value): string
    {
        return bcdiv($value, '1', 2);
    }
}
