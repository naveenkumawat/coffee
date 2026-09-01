<?php

namespace App\Services\Checkout;

use App\Models\Cart;
use App\Models\Order;
use App\Models\User;
use App\Transfers\Checkout\CheckoutTransferInterface;

interface CheckoutServiceInterface
{
    /**
     * @return array{cart: Cart, summary: array<string, mixed>}
     */
    public function getCheckoutContext(User $customer, ?string $fulfilmentMethod = null): array;

    public function placeOrder(User $customer, CheckoutTransferInterface $data, ?string $expectedCheckoutToken): Order;
}
