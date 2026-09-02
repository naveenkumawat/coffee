<?php

namespace App\Enums;

enum InventoryTransactionType: string
{
    case OpeningBalance = 'opening_balance';
    case StockAdded = 'stock_added';
    case Purchase = 'purchase';
    case ManualAdjustment = 'manual_adjustment';
    case ManualAddition = 'manual_addition';
    case ManualReduction = 'manual_reduction';
    case Wastage = 'wastage';
    case Damage = 'damage';
    case Expiry = 'expiry';
    case Correction = 'correction';
    case SaleConsumption = 'sale_consumption';
    case SaleReversal = 'sale_reversal';

    public function label(): string
    {
        return match ($this) {
            self::OpeningBalance => 'Opening Balance',
            self::StockAdded => 'Stock Added',
            self::Purchase => 'Purchase',
            self::ManualAdjustment => 'Manual Adjustment',
            self::ManualAddition => 'Manual Addition',
            self::ManualReduction => 'Manual Reduction',
            self::Wastage => 'Wastage',
            self::Damage => 'Damage',
            self::Expiry => 'Expiry',
            self::Correction => 'Correction',
            self::SaleConsumption => 'Sale Consumption',
            self::SaleReversal => 'Sale Reversal',
        };
    }

    public function isAbsoluteAdjustment(): bool
    {
        return match ($this) {
            self::ManualAdjustment, self::Correction, self::OpeningBalance => true,
            default => false,
        };
    }

    public function isIncrease(): bool
    {
        return match ($this) {
            self::StockAdded, self::Purchase, self::ManualAddition, self::SaleReversal => true,
            default => false,
        };
    }

    public function isDecrease(): bool
    {
        return match ($this) {
            self::ManualReduction, self::Wastage, self::Damage, self::Expiry, self::SaleConsumption => true,
            default => false,
        };
    }

    public static function mutationOptions(): array
    {
        return collect(self::cases())
            ->reject(fn (self $type): bool => in_array($type, [
                self::OpeningBalance,
                self::SaleConsumption,
                self::SaleReversal,
            ], true))
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])
            ->all();
    }

    public static function historyOptions(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])
            ->all();
    }
}
