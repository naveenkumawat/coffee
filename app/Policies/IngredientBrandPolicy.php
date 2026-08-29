<?php

namespace App\Policies;

use App\Models\IngredientBrand;
use App\Models\User;

class IngredientBrandPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageIngredients();
    }

    public function view(User $user, IngredientBrand $ingredientBrand): bool
    {
        return $user->canManageIngredients();
    }

    public function create(User $user): bool
    {
        return $user->canManageIngredients();
    }

    public function update(User $user, IngredientBrand $ingredientBrand): bool
    {
        return $user->canManageIngredients();
    }

    public function delete(User $user, IngredientBrand $ingredientBrand): bool
    {
        return $user->canManageIngredients();
    }
}
