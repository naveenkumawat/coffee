<?php

namespace App\Services\OrderSecurity;

use App\Models\Cart;
use App\Models\Order;
use App\Models\User;
use App\Transfers\Checkout\CheckoutTransferInterface;

interface OrderSecurityServiceInterface
{
    /**
     * @return array{
     *     enabled: bool,
     *     max_open_unpaid_orders: int,
     *     max_orders_per_hour: int,
     *     checkout_attempts_per_10_minutes: int,
     *     payment_proof_attempts_per_15_minutes: int,
     *     duplicate_order_window_minutes: int
     * }
     */
    public function config(): array;

    public function assertCustomerMayOrder(User $customer): void;

    public function assertCheckoutAttemptAllowed(User $customer): void;

    public function assertOpenUnpaidLimit(User $customer): void;

    public function assertOrderCreateRateLimit(User $customer): void;

    public function countOpenUnpaidOrders(User $customer): int;

    /**
     * @param  array{cart: Cart, summary: array<string, mixed>}  $context
     */
    public function findRecentDuplicate(User $customer, CheckoutTransferInterface $data, array $context): ?Order;

    /**
     * @param  array{cart: Cart, summary: array<string, mixed>}  $context
     */
    public function rememberOrderFingerprint(User $customer, CheckoutTransferInterface $data, array $context, Order $order): void;

    public function hitSuccessfulOrderCreate(User $customer): void;

    public function assertPaymentProofUploadAllowed(User $customer, Order $order): void;
}
