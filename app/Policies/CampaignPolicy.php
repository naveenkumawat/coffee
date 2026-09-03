<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

class CampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageWebsiteSettings();
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return $user->canManageWebsiteSettings();
    }

    public function create(User $user): bool
    {
        return $user->canManageWebsiteSettings();
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->canManageWebsiteSettings();
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $user->canManageWebsiteSettings();
    }
}
