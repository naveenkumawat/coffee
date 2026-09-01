<?php

namespace App\Services\Checkout;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\User;
use App\Repositories\Order\OrderRepositoryInterface;
use App\Services\CafeAvailability\CafeAvailabilityServiceInterface;
use App\Services\Cart\CartServiceInterface;
use App\Services\Order\OrderServiceInterface;
use App\Services\OrderSecurity\OrderSecurityServiceInterface;
use App\Transfers\Checkout\CheckoutTransferInterface;
use App\Transfers\Order\OrderTransferInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutService implements CheckoutServiceInterface
{
    public function __construct(
        protected CartServiceInterface $cartService,
        protected OrderRepositoryInterface $orders,
        protected OrderServiceInterface $orderService,
        protected OrderTransferInterface $orderTransfer,
        protected OrderSecurityServiceInterface $orderSecurity,
        protected CafeAvailabilityServiceInterface $cafeAvailability,
    ) {}

    public function getCheckoutContext(User $customer, ?string $fulfilmentMethod = null): array
    {
        $cart = $this->cartService->getForCustomer($customer);
        $summary = $this->cartService->summarize($cart, $fulfilmentMethod);

        $this->ensureCartIsCheckoutReady($cart, $summary);

        return [
            'cart' => $cart,
            'summary' => $summary,
        ];
    }

    public function placeOrder(User $customer, CheckoutTransferInterface $data, ?string $expectedCheckoutToken): Order
    {
        return DB::transaction(function () use ($customer, $data, $expectedCheckoutToken): Order {
            $checkoutToken = $data->getCheckoutToken();

            if (filled($checkoutToken)) {
                $existingOrder = $this->orders->findByCheckoutToken((string) $checkoutToken);

                if ($existingOrder) {
                    if ((int) $existingOrder->customer_id !== (int) $customer->getKey()) {
                        throw ValidationException::withMessages([
                            'checkout' => 'This checkout request does not belong to the authenticated customer.',
                        ]);
                    }

                    return $existingOrder;
                }
            }

            if (
                ! filled($expectedCheckoutToken)
                || ! filled($checkoutToken)
                || ! hash_equals((string) $expectedCheckoutToken, (string) $checkoutToken)
            ) {
                throw ValidationException::withMessages([
                    'checkout' => 'This checkout session has expired. Please review your cart and try again.',
                ]);
            }

            /** @var User $lockedCustomer */
            $lockedCustomer = User::query()
                ->whereKey($customer->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->orderSecurity->assertCustomerMayOrder($lockedCustomer);
            $this->orderSecurity->assertCheckoutAttemptAllowed($lockedCustomer);
            $this->cafeAvailability->assertOrderingAvailable();
            $this->orderSecurity->assertOpenUnpaidLimit($lockedCustomer);
            $this->orderSecurity->assertOrderCreateRateLimit($lockedCustomer);

            $context = $this->getCheckoutContext($lockedCustomer, $data->getFulfilmentMethod());
            /** @var Cart $cart */
            $cart = $context['cart'];

            $duplicate = $this->orderSecurity->findRecentDuplicate($lockedCustomer, $data, $context);

            if ($duplicate !== null) {
                $this->cartService->clear($lockedCustomer);

                return $duplicate;
            }

            $orderTransfer = clone $this->orderTransfer;
            $orderTransfer->setCustomerId((int) $lockedCustomer->getKey());
            $orderTransfer->setCheckoutToken((string) $checkoutToken);
            $orderTransfer->setCustomerName($data->getCustomerName());
            $orderTransfer->setCustomerEmail($data->getCustomerEmail());
            $orderTransfer->setCustomerPhone($data->getCustomerPhone());
            $orderTransfer->setPickupName($data->getPickupName());
            $orderTransfer->setPickupPhone($data->getPickupPhone());
            $orderTransfer->setCustomerNotes($data->getCustomerNotes());
            $orderTransfer->setPickupNotes($data->getPickupNotes());
            $orderTransfer->setFulfilmentMethod($data->getFulfilmentMethod());
            $orderTransfer->setDeliveryAddress($data->getDeliveryAddress());
            $orderTransfer->setDeliveryPhone($data->getDeliveryPhone());
            $orderTransfer->setDeliveryContactName($data->getDeliveryContactName());
            $orderTransfer->setDeliveryNotes($data->getDeliveryNotes());
            $orderTransfer->setCafeTableId($data->getCafeTableId());
            $orderTransfer->setPaymentMethod($data->getPaymentMethod());
            $orderTransfer->setPromoCode($cart->promo_code);
            $orderTransfer->setReferralFreeDrinkRewardId(
                $cart->referral_free_drink_reward_id !== null ? (int) $cart->referral_free_drink_reward_id : null,
            );
            $orderTransfer->setReferralCouponRewardId(
                $cart->referral_coupon_reward_id !== null ? (int) $cart->referral_coupon_reward_id : null,
            );
            $orderTransfer->setItems(
                $cart->items
                    ->map(fn (CartItem $item): array => [
                        'product_variant_id' => (int) $item->product_variant_id,
                        'quantity' => (int) $item->quantity,
                    ])
                    ->values()
                    ->all(),
            );

            try {
                $order = $this->orderService->store($lockedCustomer, $orderTransfer);
            } catch (UniqueConstraintViolationException $exception) {
                $existingOrder = $this->orders->findByCheckoutToken((string) $checkoutToken);

                if ($existingOrder && (int) $existingOrder->customer_id === (int) $lockedCustomer->getKey()) {
                    return $existingOrder;
                }

                throw $exception;
            }

            $this->orderSecurity->rememberOrderFingerprint($lockedCustomer, $data, $context, $order);
            $this->orderSecurity->hitSuccessfulOrderCreate($lockedCustomer);
            $this->cartService->clear($lockedCustomer);

            return $order;
        });
    }

    protected function ensureCartIsCheckoutReady(Cart $cart, array $summary): void
    {
        if ($cart->items->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => 'Your cart is empty. Add a few items before checkout.',
            ]);
        }

        if ((bool) ($summary['has_unavailable_items'] ?? false)) {
            throw ValidationException::withMessages([
                'cart' => 'Please remove unavailable items before checkout.',
            ]);
        }
    }
}
