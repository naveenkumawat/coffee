<?php

namespace App\Enums;

enum HomeSectionPlacement: string
{
    case Home = 'home';
    case Menu = 'menu';

    public function label(): string
    {
        return match ($this) {
            self::Home => 'Home',
            self::Menu => 'Menu landing',
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
