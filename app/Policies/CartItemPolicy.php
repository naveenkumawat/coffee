<?php

namespace App\Policies;

use App\Models\CartItem;
use App\Models\User;

class CartItemPolicy
{
    public function view(User $user, CartItem $cartItem): bool
    {
        return $user->hasRole('customer') && (int) $cartItem->cart?->customer_id === (int) $user->getKey();
    }

    public function update(User $user, CartItem $cartItem): bool
    {
        return $this->view($user, $cartItem);
    }

    public function delete(User $user, CartItem $cartItem): bool
    {
        return $this->view($user, $cartItem);
    }
}
