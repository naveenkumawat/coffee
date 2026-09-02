<?php

namespace App\Policies;

use App\Models\AddOn;
use App\Models\User;

class AddOnPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageProducts();
    }

    public function view(User $user, AddOn $addOn): bool
    {
        return $user->canManageProducts();
    }

    public function create(User $user): bool
    {
        return $user->canManageProducts();
    }

    public function update(User $user, AddOn $addOn): bool
    {
        return $user->canManageProducts();
    }

    public function delete(User $user, AddOn $addOn): bool
    {
        return $user->canManageProducts();
    }
}
