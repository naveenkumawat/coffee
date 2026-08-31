<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\User;
use App\Transfers\Order\OrderStatusTransitionTransferInterface;
use App\Transfers\Order\OrderTransferInterface;
use Illuminate\Http\UploadedFile;

interface OrderServiceInterface
{
    public function store(User $actor, OrderTransferInterface $data): Order;

    public function transition(Order $order, User $actor, OrderStatusTransitionTransferInterface $data): Order;

    public function uploadPaymentProof(Order $order, User $customer, UploadedFile $file): Order;

    public function rejectPaymentProof(Order $order, User $actor, ?string $notes = null): Order;

    public function markCashReceived(Order $order, User $actor): Order;

    public function availableTransitions(Order $order, User $actor): array;
}
