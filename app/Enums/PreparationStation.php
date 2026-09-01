<?php

namespace App\Enums;

enum PreparationStation: string
{
    case Bar = 'bar';
    case Kitchen = 'kitchen';

    public function label(): string
    {
        return match ($this) {
            self::Bar => 'Bar',
            self::Kitchen => 'Kitchen',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $station): array => [$station->value => $station->label()])
            ->all();
    }
}
