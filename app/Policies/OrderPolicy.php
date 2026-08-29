<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canViewOrders() || $user->hasRole('customer');
    }

    public function view(User $user, Order $order): bool
    {
        return $user->canViewOrders()
            || ($user->hasRole('customer') && (int) $order->customer_id === (int) $user->getKey());
    }

    public function create(User $user): bool
    {
        return $user->canManageOrders();
    }

    public function transition(User $user, Order $order): bool
    {
        return $user->canManageOrders() || $user->canOperateOrders();
    }
}
