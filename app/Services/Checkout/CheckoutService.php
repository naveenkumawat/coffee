<?php

namespace App\Services\Checkout;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\User;
use App\Repositories\Order\OrderRepositoryInterface;
use App\Services\Cart\CartServiceInterface;
use App\Services\Order\OrderServiceInterface;
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
    ) {}

    public function getCheckoutContext(User $customer): array
    {
        $cart = $this->cartService->getForCustomer($customer);
        $summary = $this->cartService->summarize($cart);

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

            $context = $this->getCheckoutContext($customer);
            /** @var Cart $cart */
            $cart = $context['cart'];

            $orderTransfer = clone $this->orderTransfer;
            $orderTransfer->setCustomerId((int) $customer->getKey());
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
                $order = $this->orderService->store($customer, $orderTransfer);
            } catch (UniqueConstraintViolationException $exception) {
                $existingOrder = $this->orders->findByCheckoutToken((string) $checkoutToken);

                if ($existingOrder && (int) $existingOrder->customer_id === (int) $customer->getKey()) {
                    return $existingOrder;
                }

                throw $exception;
            }

            $this->cartService->clear($customer);

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
