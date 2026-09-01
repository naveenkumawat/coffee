<?php

namespace App\Enums;

enum ProductType: string
{
    case Beverage = 'beverage';
    case Food = 'food';

    public function label(): string
    {
        return match ($this) {
            self::Beverage => 'Beverage',
            self::Food => 'Food',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])
            ->all();
    }
}
