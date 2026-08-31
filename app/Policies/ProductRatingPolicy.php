<?php

namespace App\Policies;

use App\Models\ProductRating;
use App\Models\User;

class ProductRatingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('customer') || $user->canViewProducts();
    }

    public function view(User $user, ProductRating $rating): bool
    {
        if ($user->canViewProducts()) {
            return true;
        }

        return $user->hasRole('customer') && (int) $rating->customer_id === (int) $user->getKey();
    }

    public function create(User $user): bool
    {
        return $user->hasRole('customer');
    }

    public function update(User $user, ProductRating $rating): bool
    {
        return $user->hasRole('customer') && (int) $rating->customer_id === (int) $user->getKey();
    }

    public function delete(User $user, ProductRating $rating): bool
    {
        if ($user->canManageProducts()) {
            return true;
        }

        return $user->hasRole('customer') && (int) $rating->customer_id === (int) $user->getKey();
    }

    public function moderate(User $user): bool
    {
        return $user->canManageProducts();
    }
}
