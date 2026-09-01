<?php

namespace App\Policies;

use App\Models\CustomerReferral;
use App\Models\User;

class CustomerReferralPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageWebsiteSettings();
    }

    public function view(User $user, CustomerReferral $customerReferral): bool
    {
        return $user->canManageWebsiteSettings();
    }
}
