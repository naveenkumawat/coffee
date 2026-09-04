<?php

namespace App\Policies;

use App\Models\AudienceSegment;
use App\Models\User;

class AudienceSegmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageWebsiteSettings();
    }

    public function view(User $user, AudienceSegment $audienceSegment): bool
    {
        return $user->canManageWebsiteSettings();
    }

    public function create(User $user): bool
    {
        return $user->canManageWebsiteSettings();
    }

    public function update(User $user, AudienceSegment $audienceSegment): bool
    {
        return $user->canManageWebsiteSettings();
    }

    public function delete(User $user, AudienceSegment $audienceSegment): bool
    {
        return $user->canManageWebsiteSettings();
    }
}
