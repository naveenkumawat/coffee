<?php

namespace App\Models\Concerns;

use App\Enums\PreparationStation;
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

    public function canManageUsers(): bool
    {
        $role = $this->role instanceof UserRole ? $this->role : UserRole::from($this->role);

        return $role->canManageUsers();
    }

    public function canManageWebsiteSettings(): bool
    {
        $role = $this->role instanceof UserRole ? $this->role : UserRole::from($this->role);

        return $role->canManageWebsiteSettings();
    }

    public function canManageIngredients(): bool
    {
        $role = $this->role instanceof UserRole ? $this->role : UserRole::from($this->role);

        return $role->canManageIngredients();
    }

    public function canManageProducts(): bool
    {
        $role = $this->role instanceof UserRole ? $this->role : UserRole::from($this->role);

        return $role->canManageProducts();
    }

    public function canViewProducts(): bool
    {
        $role = $this->role instanceof UserRole ? $this->role : UserRole::from($this->role);

        return $role->canViewProducts();
    }

    public function canViewInventory(): bool
    {
        $role = $this->role instanceof UserRole ? $this->role : UserRole::from($this->role);

        return $role->canViewInventory();
    }

    public function canManageOrders(): bool
    {
        $role = $this->role instanceof UserRole ? $this->role : UserRole::from($this->role);

        return $role->canManageOrders();
    }

    public function canViewOrders(): bool
    {
        $role = $this->role instanceof UserRole ? $this->role : UserRole::from($this->role);

        return $role->canViewOrders();
    }

    public function canOperateOrders(): bool
    {
        $role = $this->role instanceof UserRole ? $this->role : UserRole::from($this->role);

        return $role->canOperateOrders();
    }

    public function canOperateDining(): bool
    {
        $role = $this->role instanceof UserRole ? $this->role : UserRole::from($this->role);

        return $role->canOperateDining();
    }

    public function canPrepareStation(PreparationStation $station): bool
    {
        $role = $this->role instanceof UserRole ? $this->role : UserRole::from($this->role);

        return $role->canPrepareStation($station);
    }

    public function canAccessOperatorPanel(): bool
    {
        $role = $this->role instanceof UserRole ? $this->role : UserRole::from($this->role);

        return $role->canAccessOperatorPanel();
    }

    public function canAccessBaristaPanel(): bool
    {
        $role = $this->role instanceof UserRole ? $this->role : UserRole::from($this->role);

        return $role->canAccessBaristaPanel();
    }

    public function canAccessChefPanel(): bool
    {
        $role = $this->role instanceof UserRole ? $this->role : UserRole::from($this->role);

        return $role->canAccessChefPanel();
    }

    public function canAccessWaiterPanel(): bool
    {
        $role = $this->role instanceof UserRole ? $this->role : UserRole::from($this->role);

        return $role->canAccessWaiterPanel();
    }

    public function canAccessInternalPanel(string $panel): bool
    {
        $role = $this->role instanceof UserRole ? $this->role : UserRole::from($this->role);

        return match ($panel) {
            'administrator' => $role->canAccessAdministratorPanel(),
            'operator' => $role->canAccessOperatorPanel(),
            'barista' => $role->canAccessBaristaPanel(),
            'chef' => $role->canAccessChefPanel(),
            'waiter' => $role->canAccessWaiterPanel(),
            default => false,
        };
    }

    public function isAdministratorRole(): bool
    {
        $role = $this->role instanceof UserRole ? $this->role : UserRole::from($this->role);

        return $role->isAdministratorRole();
    }

    public function managementRoleLabel(): string
    {
        $role = $this->role instanceof UserRole ? $this->role : UserRole::from($this->role);

        return $role->managementLabel();
    }
}
