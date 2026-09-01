<?php

namespace App\Policies;

use App\Enums\PreparationStation;
use App\Models\OrderPreparation;
use App\Models\User;

class OrderPreparationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canPrepareStation(PreparationStation::Bar)
            || $user->canPrepareStation(PreparationStation::Kitchen)
            || $user->canOperateOrders()
            || $user->canManageOrders();
    }

    public function view(User $user, OrderPreparation $orderPreparation): bool
    {
        if ($user->canOperateOrders() || $user->canManageOrders()) {
            return true;
        }

        return $orderPreparation->station instanceof PreparationStation
            && $user->canPrepareStation($orderPreparation->station);
    }

    public function transition(User $user, OrderPreparation $orderPreparation): bool
    {
        return $orderPreparation->station instanceof PreparationStation
            && $user->canPrepareStation($orderPreparation->station);
    }
}
