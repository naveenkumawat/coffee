<?php

namespace App\Policies;

use App\Models\MenuItem;
use App\Models\User;

class MenuItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessAdminPanel();
    }

    public function view(User $user, MenuItem $menuItem): bool
    {
        return $user->canAccessAdminPanel();
    }

    public function create(User $user): bool
    {
        return $user->canManageMenuCatalog();
    }

    public function update(User $user, MenuItem $menuItem): bool
    {
        return $user->canManageMenuCatalog();
    }

    public function delete(User $user, MenuItem $menuItem): bool
    {
        return $user->canManageMenuCatalog();
    }
}
