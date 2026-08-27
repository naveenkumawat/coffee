<?php

namespace App\Policies;

use App\Models\MenuCategory;
use App\Models\User;

class MenuCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessAdminPanel();
    }

    public function view(User $user, MenuCategory $menuCategory): bool
    {
        return $user->canAccessAdminPanel();
    }

    public function create(User $user): bool
    {
        return $user->canManageMenuCatalog();
    }

    public function update(User $user, MenuCategory $menuCategory): bool
    {
        return $user->canManageMenuCatalog();
    }

    public function delete(User $user, MenuCategory $menuCategory): bool
    {
        return $user->canManageMenuCatalog() && $menuCategory->menuItems()->doesntExist();
    }
}
