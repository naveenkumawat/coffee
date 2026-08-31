<?php

namespace App\Enums;

enum ProductTagStyle: string
{
    case Primary = 'primary';
    case Accent = 'accent';
    case Soft = 'soft';
    case Warning = 'warning';
    case Muted = 'muted';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::Primary->value => 'Primary',
            self::Accent->value => 'Accent',
            self::Soft->value => 'Soft',
            self::Warning->value => 'Warning',
            self::Muted->value => 'Muted',
        ];
    }
}
