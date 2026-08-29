<?php

namespace App\Policies;

use App\Models\Recipe;
use App\Models\User;

class RecipePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canViewProducts();
    }

    public function view(User $user, Recipe $recipe): bool
    {
        if (! $user->canViewProducts()) {
            return false;
        }

        if ($user->canManageProducts()) {
            return true;
        }

        return $recipe->is_active && (bool) $recipe->variant?->is_active && (bool) $recipe->variant?->product?->is_active;
    }

    public function create(User $user): bool
    {
        return $user->canManageProducts();
    }

    public function update(User $user, Recipe $recipe): bool
    {
        return $user->canManageProducts();
    }

    public function delete(User $user, Recipe $recipe): bool
    {
        return $user->canManageProducts();
    }
}
