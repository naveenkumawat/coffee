<?php

namespace App\Enums;

enum AudienceSegmentActor: string
{
    case Visitor = 'visitor';
    case Customer = 'customer';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Visitor => 'Visitors only',
            self::Customer => 'Customers only',
            self::Both => 'Visitors and customers',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
