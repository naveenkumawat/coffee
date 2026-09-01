<?php

namespace App\Policies;

use App\Models\CafeClosure;
use App\Models\User;

class CafeClosurePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageWebsiteSettings() || $user->canViewOrders();
    }

    public function view(User $user, CafeClosure $cafeClosure): bool
    {
        return $user->canManageWebsiteSettings() || $user->canViewOrders();
    }

    public function create(User $user): bool
    {
        return $user->canManageWebsiteSettings();
    }

    public function update(User $user, CafeClosure $cafeClosure): bool
    {
        return $user->canManageWebsiteSettings();
    }

    public function delete(User $user, CafeClosure $cafeClosure): bool
    {
        return $user->canManageWebsiteSettings();
    }
}
