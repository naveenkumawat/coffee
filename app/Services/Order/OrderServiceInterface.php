<?php

namespace App\Services\Order;

use App\Enums\PaymentMethod;
use App\Models\DiningSession;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Transfers\Order\OrderStatusTransitionTransferInterface;
use App\Transfers\Order\OrderTransferInterface;

interface OrderServiceInterface
{
    public function store(User $actor, OrderTransferInterface $data): Order;

    /**
     * @param  list<array{product_variant_id: int, quantity: int}>  $items
     */
    public function placeDiningRound(User $actor, DiningSession $session, array $items, ?string $customerNotes = null): Order;

    public function transition(Order $order, User $actor, OrderStatusTransitionTransferInterface $data): Order;

    /**
     * Customer cancels own unpaid retail Pending Payment order.
     * Idempotent when already cancelled by the same customer ownership.
     */
    public function cancelPendingPaymentByCustomer(Order $order, User $customer): Order;

    /**
     * System auto-cancel for expired unpaid retail Pending Payment orders.
     * Idempotent; rechecks payment/status under lock.
     */
    public function expirePendingPaymentOrder(Order $order): Order;

    /**
     * Expire all due unpaid retail pending orders (scheduler/command).
     *
     * @return int Number of orders cancelled in this run
     */
    public function expireDuePendingPaymentOrders(int $limit = 100): int;

    /**
     * Expire due pending orders for one customer (used before pending-limit checks).
     *
     * @return int Number of orders cancelled
     */
    public function expireDuePendingPaymentOrdersForCustomer(User $customer): int;

    /**
     * Canonical eligibility: customer may cancel unpaid Pending Payment retail order.
     */
    public function canCustomerCancel(Order $order, User $customer): bool;

    /**
     * Terminal cancel for a dining round (L1.2). Idempotent when already Cancelled.
     * Bypasses retail availableTransitions so Ready dining rounds can be cancelled under policy.
     */
    public function cancelDiningRound(Order $order, User $actor, ?string $notes = null): Order;

    public function uploadPaymentProof(Order $order, User $customer, string $transactionId): Order;

    public function rejectPaymentProof(Order $order, User $actor, ?string $notes = null): Order;

    public function markCashReceived(Order $order, User $actor): Order;

    /**
     * Confirm unpaid order from a verified online gateway payment attempt.
     */
    public function confirmGatewayPayment(Order $order, PaymentAttempt $attempt, PaymentMethod $method): Order;

    public function availableTransitions(Order $order, User $actor): array;
}
