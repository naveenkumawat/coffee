<?php

namespace App\Enums;

enum IngredientUnit: string
{
    case Gram = 'g';
    case Kilogram = 'kg';
    case Milliliter = 'ml';
    case Liter = 'L';
    case Piece = 'piece';
    case Bottle = 'bottle';
    case Pack = 'pack';

    public function label(): string
    {
        return $this->value;
    }

    public function baseUnit(): self
    {
        return match ($this) {
            self::Kilogram => self::Gram,
            self::Liter => self::Milliliter,
            default => $this,
        };
    }

    public function multiplier(): string
    {
        return match ($this) {
            self::Kilogram, self::Liter => '1000',
            default => '1',
        };
    }

    public function normalize(string $quantity, int $scale = 3): string
    {
        return bcdiv(
            bcmul($quantity, $this->multiplier(), $scale + 3),
            '1',
            $scale,
        );
    }

    public function supportsBaseUnit(self $baseUnit): bool
    {
        return $this->baseUnit() === $baseUnit;
    }

    public static function optionsForBaseUnit(self $baseUnit): array
    {
        return collect(self::cases())
            ->filter(fn (self $unit): bool => $unit->supportsBaseUnit($baseUnit))
            ->mapWithKeys(fn (self $unit): array => [$unit->value => $unit->label()])
            ->all();
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $unit): array => [$unit->value => $unit->label()])
            ->all();
    }
}
