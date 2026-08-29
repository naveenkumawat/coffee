<?php

namespace App\Services\Checkout;

use App\Models\Order;
use App\Models\User;
use App\Transfers\Checkout\CheckoutTransferInterface;

interface CheckoutServiceInterface
{
    public function getCheckoutContext(User $customer): array;

    public function placeOrder(User $customer, CheckoutTransferInterface $data, ?string $expectedCheckoutToken): Order;
}
