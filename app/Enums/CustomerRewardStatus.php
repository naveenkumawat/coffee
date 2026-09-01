<?php

namespace App\Enums;

enum CustomerRewardStatus: string
{
    case Available = 'available';
    case Reserved = 'reserved';
    case Redeemed = 'redeemed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Reserved => 'Reserved',
            self::Redeemed => 'Redeemed',
            self::Expired => 'Expired',
            self::Cancelled => 'Cancelled',
        };
    }
}
