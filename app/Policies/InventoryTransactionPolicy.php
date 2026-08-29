<?php

namespace App\Policies;

use App\Models\InventoryTransaction;
use App\Models\User;

class InventoryTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canViewInventory();
    }

    public function view(User $user, InventoryTransaction $inventoryTransaction): bool
    {
        return $user->canViewInventory();
    }

    public function create(User $user): bool
    {
        return $user->canManageIngredients();
    }
}
