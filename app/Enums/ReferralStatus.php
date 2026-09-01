<?php

namespace App\Enums;

enum ReferralStatus: string
{
    case Registered = 'registered';
    case Qualified = 'qualified';
    case Rewarded = 'rewarded';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Registered => 'Registered',
            self::Qualified => 'Qualified',
            self::Rewarded => 'Rewarded',
            self::Cancelled => 'Cancelled',
        };
    }
}
