<?php

namespace App\Enums;

enum UserRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Barista = 'barista';
    case Waiter = 'waiter';
    case Cashier = 'cashier';
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Manager => 'Manager',
            self::Barista => 'Barista',
            self::Waiter => 'Waiter',
            self::Cashier => 'Cashier',
            self::Customer => 'Customer',
        };
    }

    public function canAccessAdmin(): bool
    {
        return match ($this) {
            self::Owner, self::Manager, self::Barista, self::Waiter, self::Cashier => true,
            self::Customer => false,
        };
    }

    public function isAdministratorRole(): bool
    {
        return match ($this) {
            self::Owner, self::Manager => true,
            self::Barista, self::Waiter, self::Cashier, self::Customer => false,
        };
    }

    public function canManageMenu(): bool
    {
        return match ($this) {
            self::Owner, self::Manager => true,
            default => false,
        };
    }

    public function canManageUsers(): bool
    {
        return match ($this) {
            self::Owner, self::Manager => true,
            default => false,
        };
    }

    public function canManageWebsiteSettings(): bool
    {
        return match ($this) {
            self::Owner, self::Manager => true,
            default => false,
        };
    }

    public function canManageIngredients(): bool
    {
        return match ($this) {
            self::Owner, self::Manager => true,
            default => false,
        };
    }

    public function canManageProducts(): bool
    {
        return match ($this) {
            self::Owner, self::Manager => true,
            default => false,
        };
    }

    public function canViewProducts(): bool
    {
        return match ($this) {
            self::Owner, self::Manager, self::Barista, self::Waiter => true,
            default => false,
        };
    }

    public function canViewInventory(): bool
    {
        return match ($this) {
            self::Owner, self::Manager, self::Barista => true,
            default => false,
        };
    }

    public function canManageOrders(): bool
    {
        return match ($this) {
            self::Owner, self::Manager => true,
            default => false,
        };
    }

    public function canViewOrders(): bool
    {
        return match ($this) {
            self::Owner, self::Manager, self::Barista, self::Waiter => true,
            default => false,
        };
    }

    public function canOperateOrders(): bool
    {
        return match ($this) {
            self::Barista => true,
            default => false,
        };
    }

    public function canOperateDining(): bool
    {
        return match ($this) {
            self::Waiter, self::Owner, self::Manager => true,
            default => false,
        };
    }

    public function canAccessAdministratorPanel(): bool
    {
        return match ($this) {
            self::Owner, self::Manager => true,
            default => false,
        };
    }

    public function canAccessBaristaPanel(): bool
    {
        return match ($this) {
            self::Barista => true,
            default => false,
        };
    }

    public function canAccessWaiterPanel(): bool
    {
        return match ($this) {
            self::Waiter => true,
            default => false,
        };
    }

    public function managementLabel(): string
    {
        return match ($this) {
            self::Owner, self::Manager => 'Administrator',
            self::Barista => 'Barista',
            self::Waiter => 'Waiter',
            self::Customer => 'Customer',
            self::Cashier => 'Cashier',
        };
    }
}
