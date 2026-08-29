<?php

namespace App\Services\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Collection;

interface RoleServiceInterface
{
    public function all(): Collection;

    public function labels(): array;

    public function allowedAdminRoles(): array;

    public function canAccessAdmin(User $user): bool;

    public function administratorRoleValues(): array;

    public function userManagementRoleOptions(?User $user = null): array;

    public function userManagementFilterOptions(): array;

    public function normalizeUserManagementRoleValue(UserRole|string|null $role): ?string;

    public function isAdministratorValue(string $role): bool;
}
