<?php

namespace App\Enums;

enum LoyaltyTransactionType: string
{
    case Earn = 'earn';
    case Redeem = 'redeem';
    case Reversal = 'reversal';
    case Adjustment = 'adjustment';
    case Expiry = 'expiry';

    public function label(): string
    {
        return match ($this) {
            self::Earn => 'Earned',
            self::Redeem => 'Redeemed',
            self::Reversal => 'Reversed',
            self::Adjustment => 'Adjustment',
            self::Expiry => 'Expired',
        };
    }

    public function customerLabel(): string
    {
        return match ($this) {
            self::Earn => 'Points earned',
            self::Redeem => 'Points redeemed',
            self::Reversal => 'Points reversed',
            self::Adjustment => 'Balance adjustment',
            self::Expiry => 'Points expired',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
