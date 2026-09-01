<?php

namespace App\Enums;

enum CafeAvailabilityCode: string
{
    case Open = 'open';
    case OutsideHours = 'outside_hours';
    case Holiday = 'holiday';
    case ScheduledClosure = 'scheduled_closure';
    case ManualClosed = 'manual_closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::OutsideHours => 'Outside hours',
            self::Holiday => 'Holiday',
            self::ScheduledClosure => 'Scheduled closure',
            self::ManualClosed => 'Out of service',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Open => 'badge-light-success',
            self::OutsideHours => 'badge-light-warning',
            self::Holiday, self::ScheduledClosure, self::ManualClosed => 'badge-light-danger',
        };
    }
}
