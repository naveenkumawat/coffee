<?php

namespace App\Enums;

enum DiningServiceRequestType: string
{
    case OrderAssistance = 'order_assistance';

    public function label(): string
    {
        return match ($this) {
            self::OrderAssistance => 'Order assistance',
        };
    }
}
