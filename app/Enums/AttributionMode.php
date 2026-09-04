<?php

namespace App\Enums;

enum AttributionMode: string
{
    case Direct = 'direct';
    case ViewThrough = 'view_through';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
