<?php

namespace App\Policies;

use App\Models\Cart;
use App\Models\User;

class CartPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('customer');
    }

    public function view(User $user, Cart $cart): bool
    {
        return $user->hasRole('customer') && (int) $cart->customer_id === (int) $user->getKey();
    }

    public function create(User $user): bool
    {
        return $user->hasRole('customer');
    }
}
