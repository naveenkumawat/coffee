<?php

namespace App\Enums;

enum CampaignSurface: string
{
    case Popup = 'popup';
    case Banner = 'banner';
    case Inline = 'inline';
    case Landing = 'landing';

    public function label(): string
    {
        return match ($this) {
            self::Popup => 'Popup / Modal',
            self::Banner => 'Banner',
            self::Inline => 'Inline block',
            self::Landing => 'Landing section',
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
