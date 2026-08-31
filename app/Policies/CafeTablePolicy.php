<?php

namespace App\Policies;

use App\Models\CafeTable;
use App\Models\User;

class CafeTablePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageWebsiteSettings();
    }

    public function view(User $user, CafeTable $cafeTable): bool
    {
        return $user->canManageWebsiteSettings();
    }

    public function create(User $user): bool
    {
        return $user->canManageWebsiteSettings();
    }

    public function update(User $user, CafeTable $cafeTable): bool
    {
        return $user->canManageWebsiteSettings();
    }

    public function delete(User $user, CafeTable $cafeTable): bool
    {
        return $user->canManageWebsiteSettings();
    }
}
