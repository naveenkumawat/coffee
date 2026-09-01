<?php

namespace App\Enums;

enum UserRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Operator = 'operator';
    case Barista = 'barista';
    case Chef = 'chef';
    case Waiter = 'waiter';
    case Cashier = 'cashier';
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Manager => 'Manager',
            self::Operator => 'Operator',
            self::Barista => 'Barista',
            self::Chef => 'Chef',
            self::Waiter => 'Waiter',
            self::Cashier => 'Cashier',
            self::Customer => 'Customer',
        };
    }

    public function canAccessAdmin(): bool
    {
        return match ($this) {
            self::Owner, self::Manager, self::Operator, self::Barista, self::Chef, self::Waiter, self::Cashier => true,
            self::Customer => false,
        };
    }

    public function isAdministratorRole(): bool
    {
        return match ($this) {
            self::Owner, self::Manager => true,
            self::Operator, self::Barista, self::Chef, self::Waiter, self::Cashier, self::Customer => false,
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
            self::Owner, self::Manager, self::Operator, self::Barista, self::Chef, self::Waiter => true,
            default => false,
        };
    }

    public function canViewInventory(): bool
    {
        return match ($this) {
            self::Owner, self::Manager, self::Operator, self::Barista => true,
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
            self::Owner, self::Manager, self::Operator, self::Barista, self::Chef, self::Waiter => true,
            default => false,
        };
    }

    public function canOperateOrders(): bool
    {
        return match ($this) {
            self::Operator => true,
            default => false,
        };
    }

    public function canOperateDining(): bool
    {
        return match ($this) {
            self::Waiter, self::Owner, self::Manager, self::Operator => true,
            default => false,
        };
    }

    public function canPrepareStation(PreparationStation $station): bool
    {
        return match ($this) {
            self::Barista => $station === PreparationStation::Bar,
            self::Chef => $station === PreparationStation::Kitchen,
            default => false,
        };
    }

    public function canAccessOperatorPanel(): bool
    {
        return match ($this) {
            self::Operator => true,
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

    public function canAccessChefPanel(): bool
    {
        return match ($this) {
            self::Chef => true,
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

    public function canAccessAdministratorPanel(): bool
    {
        return match ($this) {
            self::Owner, self::Manager => true,
            default => false,
        };
    }

    public function managementLabel(): string
    {
        return match ($this) {
            self::Owner, self::Manager => 'Administrator',
            self::Operator => 'Operator',
            self::Barista => 'Barista',
            self::Chef => 'Chef',
            self::Waiter => 'Waiter',
            self::Cashier => 'Cashier',
            self::Customer => 'Customer',
        };
    }
}
