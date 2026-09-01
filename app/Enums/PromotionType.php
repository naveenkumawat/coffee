<?php

namespace App\Enums;

enum PromotionType: string
{
    case Automatic = 'automatic';
    case Coupon = 'coupon';

    public function label(): string
    {
        return match ($this) {
            self::Automatic => 'Automatic',
            self::Coupon => 'Promo code',
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
