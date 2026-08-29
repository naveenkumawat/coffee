<?php

namespace App\Services\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Collection;

class RoleService implements RoleServiceInterface
{
    public function all(): Collection
    {
        return collect(config('roles.roles'));
    }

    public function labels(): array
    {
        return collect(UserRole::cases())
            ->mapWithKeys(fn (UserRole $role) => [$role->value => $role->label()])
            ->all();
    }

    public function allowedAdminRoles(): array
    {
        return collect(UserRole::cases())
            ->filter(fn (UserRole $role) => $role->canAccessAdmin())
            ->map(fn (UserRole $role) => $role->value)
            ->values()
            ->all();
    }

    public function canAccessAdmin(User $user): bool
    {
        return $user->canAccessAdminPanel();
    }
}
