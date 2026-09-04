<?php

namespace App\Policies;

use App\Models\LoyaltyReward;
use App\Models\User;

class LoyaltyRewardPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageWebsiteSettings();
    }

    public function view(User $user, LoyaltyReward $reward): bool
    {
        return $user->canManageWebsiteSettings();
    }

    public function create(User $user): bool
    {
        return $user->canManageWebsiteSettings();
    }

    public function update(User $user, LoyaltyReward $reward): bool
    {
        return $user->canManageWebsiteSettings();
    }

    public function delete(User $user, LoyaltyReward $reward): bool
    {
        return $user->canManageWebsiteSettings();
    }
}
