<?php

namespace App\Enums;

enum CampaignCtaType: string
{
    case Product = 'product';
    case Category = 'category';
    case InternalPage = 'internal_page';
    case Promotion = 'promotion';
    case Close = 'close';

    public function label(): string
    {
        return match ($this) {
            self::Product => 'Product',
            self::Category => 'Category',
            self::InternalPage => 'Internal page',
            self::Promotion => 'Promotion / offer',
            self::Close => 'Close / dismiss',
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
