<?php

namespace App\Enums;

enum ProductServingUnit: string
{
    case Milliliter = 'ml';
    case Gram = 'g';
    case Piece = 'piece';

    public function label(): string
    {
        return match ($this) {
            self::Milliliter => 'Milliliter (ml)',
            self::Gram => 'Gram (g)',
            self::Piece => 'Piece',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $unit): array => [$unit->value => $unit->label()])
            ->all();
    }
}
