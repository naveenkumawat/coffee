<?php

namespace App\Policies;

use App\Models\ProductFavourite;
use App\Models\User;

class ProductFavouritePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('customer');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('customer');
    }

    public function delete(User $user, ProductFavourite $favourite): bool
    {
        return $user->hasRole('customer') && (int) $favourite->customer_id === (int) $user->getKey();
    }
}
