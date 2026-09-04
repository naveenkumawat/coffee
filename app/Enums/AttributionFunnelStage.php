<?php

namespace App\Enums;

enum AttributionFunnelStage: string
{
    case CartAdded = 'cart_added';
    case Converted = 'converted';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
