<?php

namespace App\Policies;

use App\Models\Promotion;
use App\Models\User;

class PromotionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageWebsiteSettings();
    }

    public function view(User $user, Promotion $promotion): bool
    {
        return $user->canManageWebsiteSettings();
    }

    public function create(User $user): bool
    {
        return $user->canManageWebsiteSettings();
    }

    public function update(User $user, Promotion $promotion): bool
    {
        return $user->canManageWebsiteSettings();
    }

    public function delete(User $user, Promotion $promotion): bool
    {
        return $user->canManageWebsiteSettings();
    }
}
