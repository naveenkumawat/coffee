<?php

namespace App\Enums;

enum CampaignTriggerType: string
{
    case Immediate = 'immediate';
    case Delay = 'delay';
    case Scroll = 'scroll';
    case ProductViews = 'product_views';

    public function label(): string
    {
        return match ($this) {
            self::Immediate => 'Immediately on eligible page',
            self::Delay => 'After delay',
            self::Scroll => 'After scroll percentage',
            self::ProductViews => 'After N product views in session',
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
