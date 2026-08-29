<?php

namespace App\Policies;

use App\Models\ProductCategory;
use App\Models\User;

class ProductCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canViewProducts();
    }

    public function view(User $user, ProductCategory $productCategory): bool
    {
        return $user->canViewProducts();
    }

    public function create(User $user): bool
    {
        return $user->canManageProducts();
    }

    public function update(User $user, ProductCategory $productCategory): bool
    {
        return $user->canManageProducts();
    }

    public function delete(User $user, ProductCategory $productCategory): bool
    {
        return $user->canManageProducts();
    }
}
