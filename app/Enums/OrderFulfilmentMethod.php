<?php

namespace App\Enums;

enum OrderFulfilmentMethod: string
{
    case Takeaway = 'takeaway';
    case Delivery = 'delivery';
    case DineIn = 'dine_in';

    public function label(): string
    {
        return match ($this) {
            self::Takeaway => 'Takeaway',
            self::Delivery => 'Delivery',
            self::DineIn => 'Dine-in',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Takeaway => 'badge-light-dark',
            self::Delivery => 'badge-light-primary',
            self::DineIn => 'badge-light-info',
        };
    }

    public function readyLabel(): string
    {
        return match ($this) {
            self::Takeaway => 'Ready for Pickup',
            self::Delivery => 'Ready for Delivery',
            self::DineIn => 'Ready to Serve',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $method): array => [$method->value => $method->label()])
            ->all();
    }
}
