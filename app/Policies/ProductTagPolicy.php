<?php

namespace App\Policies;

use App\Models\ProductTag;
use App\Models\User;

class ProductTagPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageProducts();
    }

    public function view(User $user, ProductTag $productTag): bool
    {
        return $user->canManageProducts();
    }

    public function create(User $user): bool
    {
        return $user->canManageProducts();
    }

    public function update(User $user, ProductTag $productTag): bool
    {
        return $user->canManageProducts();
    }

    public function delete(User $user, ProductTag $productTag): bool
    {
        return $user->canManageProducts();
    }
}
