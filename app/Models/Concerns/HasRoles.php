<?php

namespace App\Models\Concerns;

use App\Enums\UserRole;

trait HasRoles
{
    public function hasRole(UserRole|string ...$roles): bool
    {
        $current = $this->role instanceof UserRole ? $this->role : UserRole::from($this->role);

        foreach ($roles as $role) {
            $expected = $role instanceof UserRole ? $role : UserRole::from($role);

            if ($current === $expected) {
                return true;
            }
        }

        return false;
    }

    public function canAccessAdminPanel(): bool
    {
        $role = $this->role instanceof UserRole ? $this->role : UserRole::from($this->role);

        return $role->canAccessAdmin();
    }

    public function canManageMenuCatalog(): bool
    {
        $role = $this->role instanceof UserRole ? $this->role : UserRole::from($this->role);

        return $role->canManageMenu();
    }
}
