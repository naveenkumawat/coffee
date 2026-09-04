<?php

namespace App\Enums;

enum LoyaltyTransactionSourceType: string
{
    case Order = 'order';
    case DiningSession = 'dining_session';
    case Referral = 'referral';
    case Admin = 'admin';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Order => 'Order',
            self::DiningSession => 'Dining session',
            self::Referral => 'Referral',
            self::Admin => 'Administrator',
            self::System => 'System',
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
