<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageUsers();
    }

    public function view(User $user, User $managedUser): bool
    {
        return $user->canManageUsers();
    }

    public function create(User $user): bool
    {
        return $user->canManageUsers();
    }

    public function update(User $user, User $managedUser): bool
    {
        return $user->canManageUsers();
    }

    public function delete(User $user, User $managedUser): bool
    {
        return $user->canManageUsers();
    }
}
