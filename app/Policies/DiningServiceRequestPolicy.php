<?php

namespace App\Policies;

use App\Models\DiningServiceRequest;
use App\Models\DiningSession;
use App\Models\User;

class DiningServiceRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canOperateDining() || $user->canManageOrders();
    }

    public function view(User $user, DiningServiceRequest $request): bool
    {
        if ($user->canOperateDining() || $user->canManageOrders()) {
            return true;
        }

        return $user->hasRole('customer')
            && $request->customer_id !== null
            && (int) $request->customer_id === (int) $user->getKey();
    }

    public function create(User $user, DiningSession $session): bool
    {
        return $user->hasRole('customer')
            && $session->customer_id !== null
            && (int) $session->customer_id === (int) $user->getKey();
    }

    public function cancel(User $user, DiningServiceRequest $request): bool
    {
        return $user->hasRole('customer')
            && $request->customer_id !== null
            && (int) $request->customer_id === (int) $user->getKey();
    }

    public function claim(User $user, DiningServiceRequest $request): bool
    {
        return $user->canOperateDining() || $user->canManageOrders();
    }

    public function complete(User $user, DiningServiceRequest $request): bool
    {
        return $user->canOperateDining() || $user->canManageOrders();
    }
}
