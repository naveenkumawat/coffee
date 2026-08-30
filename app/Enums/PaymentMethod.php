<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
        };
    }
}
