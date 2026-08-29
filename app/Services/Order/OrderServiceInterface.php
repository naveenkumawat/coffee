<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\User;
use App\Transfers\Order\OrderStatusTransitionTransferInterface;
use App\Transfers\Order\OrderTransferInterface;

interface OrderServiceInterface
{
    public function store(User $actor, OrderTransferInterface $data): Order;

    public function transition(Order $order, User $actor, OrderStatusTransitionTransferInterface $data): Order;

    public function availableTransitions(Order $order, User $actor): array;
}
