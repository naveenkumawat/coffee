<?php

namespace App\Enums;

enum UserRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Barista = 'barista';
    case Cashier = 'cashier';
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Manager => 'Manager',
            self::Barista => 'Barista',
            self::Cashier => 'Cashier',
            self::Customer => 'Customer',
        };
    }

    public function canAccessAdmin(): bool
    {
        return match ($this) {
            self::Owner, self::Manager, self::Barista, self::Cashier => true,
            self::Customer => false,
        };
    }

    public function canManageMenu(): bool
    {
        return match ($this) {
            self::Owner, self::Manager => true,
            self::Barista, self::Cashier, self::Customer => false,
        };
    }
}
