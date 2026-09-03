<?php

namespace App\Enums;

enum CampaignFrequencyPolicy: string
{
    case EverySession = 'every_session';
    case OncePerSession = 'once_per_session';
    case OncePerActor = 'once_per_actor';
    case OncePerDay = 'once_per_day';
    case Cooldown = 'cooldown';
    case MaxImpressions = 'max_impressions';

    public function label(): string
    {
        return match ($this) {
            self::EverySession => 'Every eligible session',
            self::OncePerSession => 'Once per session',
            self::OncePerActor => 'Once per visitor/customer',
            self::OncePerDay => 'Once per day',
            self::Cooldown => 'Cooldown (hours)',
            self::MaxImpressions => 'Maximum impressions',
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
