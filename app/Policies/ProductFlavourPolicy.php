<?php

namespace App\Policies;

use App\Models\ProductFlavour;
use App\Models\User;

class ProductFlavourPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canViewProducts();
    }

    public function view(User $user, ProductFlavour $productFlavour): bool
    {
        return $user->canViewProducts();
    }

    public function create(User $user): bool
    {
        return $user->canManageProducts();
    }

    public function update(User $user, ProductFlavour $productFlavour): bool
    {
        return $user->canManageProducts();
    }

    public function delete(User $user, ProductFlavour $productFlavour): bool
    {
        return $user->canManageProducts();
    }
}
