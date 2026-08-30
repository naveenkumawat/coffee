<?php

namespace App\Enums;

enum OrderFulfilmentMethod: string
{
    case Takeaway = 'takeaway';
    case Delivery = 'delivery';

    public function label(): string
    {
        return match ($this) {
            self::Takeaway => 'Takeaway',
            self::Delivery => 'Delivery',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $method): array => [$method->value => $method->label()])
            ->all();
    }
}
