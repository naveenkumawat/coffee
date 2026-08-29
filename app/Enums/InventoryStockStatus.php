<?php

namespace App\Enums;

enum InventoryStockStatus: string
{
    case InStock = 'in_stock';
    case LowStock = 'low_stock';
    case OutOfStock = 'out_of_stock';

    public function label(): string
    {
        return match ($this) {
            self::InStock => 'In Stock',
            self::LowStock => 'Low Stock',
            self::OutOfStock => 'Out of Stock',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::InStock => 'badge-light-success',
            self::LowStock => 'badge-light-warning',
            self::OutOfStock => 'badge-light-danger',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])
            ->all();
    }
}
