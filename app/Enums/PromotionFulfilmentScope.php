<?php

namespace App\Enums;

enum PromotionFulfilmentScope: string
{
    case Any = 'any';
    case DineIn = 'dine_in';
    case Takeaway = 'takeaway';
    case Delivery = 'delivery';

    public function label(): string
    {
        return match ($this) {
            self::Any => 'Any fulfilment',
            self::DineIn => 'Dine-in only',
            self::Takeaway => 'Takeaway only',
            self::Delivery => 'Delivery only',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $scope): array => [$scope->value => $scope->label()])
            ->all();
    }
}
