<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\InventoryRefillRequest;
use App\Models\User;

class InventoryRefillRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canViewInventory();
    }

    public function view(User $user, InventoryRefillRequest $inventoryRefillRequest): bool
    {
        return $user->canManageIngredients()
            || $inventoryRefillRequest->requested_by === $user->getKey();
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Barista);
    }

    public function review(User $user, InventoryRefillRequest $inventoryRefillRequest): bool
    {
        return $user->canManageIngredients();
    }
}
