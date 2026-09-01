<?php

namespace App\Enums;

enum TableOperationalStatus: string
{
    case Available = 'available';
    case Occupied = 'occupied';
    case BillRequested = 'bill_requested';
    case AwaitingPayment = 'awaiting_payment';

    public static function fromState(string $state): self
    {
        return self::tryFrom($state) ?? self::Available;
    }

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Occupied => 'Occupied',
            self::BillRequested => 'Bill Requested',
            self::AwaitingPayment => 'Awaiting Payment',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Available => 'badge-light-success',
            self::Occupied => 'badge-light-warning',
            self::BillRequested => 'badge-light-info',
            self::AwaitingPayment => 'badge-light-primary',
        };
    }
}
