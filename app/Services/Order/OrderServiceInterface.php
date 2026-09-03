<?php

namespace App\Services\Order;

use App\Models\DiningSession;
use App\Models\Order;
use App\Models\User;
use App\Transfers\Order\OrderStatusTransitionTransferInterface;
use App\Transfers\Order\OrderTransferInterface;
use Illuminate\Http\UploadedFile;

interface OrderServiceInterface
{
    public function store(User $actor, OrderTransferInterface $data): Order;

    /**
     * @param  list<array{product_variant_id: int, quantity: int}>  $items
     */
    public function placeDiningRound(User $actor, DiningSession $session, array $items, ?string $customerNotes = null): Order;

    public function transition(Order $order, User $actor, OrderStatusTransitionTransferInterface $data): Order;

    /**
     * Terminal cancel for a dining round (L1.2). Idempotent when already Cancelled.
     * Bypasses retail availableTransitions so Ready dining rounds can be cancelled under policy.
     */
    public function cancelDiningRound(Order $order, User $actor, ?string $notes = null): Order;

    public function uploadPaymentProof(Order $order, User $customer, UploadedFile $file): Order;

    public function rejectPaymentProof(Order $order, User $actor, ?string $notes = null): Order;

    public function markCashReceived(Order $order, User $actor): Order;

    public function availableTransitions(Order $order, User $actor): array;
}
