<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WebsiteSetting;

class WebsiteSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageWebsiteSettings();
    }

    public function view(User $user, WebsiteSetting $websiteSetting): bool
    {
        return $user->canManageWebsiteSettings();
    }

    public function update(User $user): bool
    {
        return $user->canManageWebsiteSettings();
    }
}
