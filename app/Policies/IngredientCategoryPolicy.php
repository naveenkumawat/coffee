<?php

namespace App\Policies;

use App\Models\IngredientCategory;
use App\Models\User;

class IngredientCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageIngredients();
    }

    public function view(User $user, IngredientCategory $ingredientCategory): bool
    {
        return $user->canManageIngredients();
    }

    public function create(User $user): bool
    {
        return $user->canManageIngredients();
    }

    public function update(User $user, IngredientCategory $ingredientCategory): bool
    {
        return $user->canManageIngredients();
    }

    public function delete(User $user, IngredientCategory $ingredientCategory): bool
    {
        return $user->canManageIngredients();
    }
}
