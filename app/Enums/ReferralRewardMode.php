<?php

namespace App\Enums;

enum ReferralRewardMode: string
{
    case FreeDrink = 'free_drink';
    case Coupon = 'coupon';

    public function label(): string
    {
        return match ($this) {
            self::FreeDrink => 'Free Drink',
            self::Coupon => 'Personal Coupon',
        };
    }
}
