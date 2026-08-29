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

    public function administratorRoleValues(): array
    {
        return collect(UserRole::cases())
            ->filter(fn (UserRole $role): bool => $role->isAdministratorRole())
            ->map(fn (UserRole $role): string => $role->value)
            ->values()
            ->all();
    }

    public function userManagementRoleOptions(?User $user = null): array
    {
        $options = [
            UserRole::Owner->value => 'Administrator',
            UserRole::Barista->value => 'Barista',
            UserRole::Customer->value => 'Customer',
        ];

        if ($user && $user->role === UserRole::Manager) {
            $options = [UserRole::Manager->value => 'Manager (legacy administrator)'] + $options;
        }

        if ($user && $user->role === UserRole::Cashier) {
            $options = [UserRole::Cashier->value => 'Cashier (legacy)'] + $options;
        }

        return $options;
    }

    public function userManagementFilterOptions(): array
    {
        return [
            'administrator' => 'Administrator',
            'barista' => 'Barista',
            'customer' => 'Customer',
        ];
    }

    public function normalizeUserManagementRoleValue(UserRole|string|null $role): ?string
    {
        if ($role === null) {
            return null;
        }

        $resolvedRole = $role instanceof UserRole ? $role : UserRole::from($role);

        return match ($resolvedRole) {
            UserRole::Owner, UserRole::Manager => UserRole::Owner->value,
            default => $resolvedRole->value,
        };
    }

    public function isAdministratorValue(string $role): bool
    {
        return in_array($role, $this->administratorRoleValues(), true);
    }
}
