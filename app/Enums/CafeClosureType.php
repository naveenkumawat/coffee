<?php

namespace App\Enums;

enum CafeClosureType: string
{
    case Holiday = 'holiday';
    case Maintenance = 'maintenance';
    case PrivateEvent = 'private_event';
    case TemporaryClosure = 'temporary_closure';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Holiday => 'Holiday',
            self::Maintenance => 'Maintenance',
            self::PrivateEvent => 'Private event',
            self::TemporaryClosure => 'Temporary closure',
            self::Other => 'Other',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Holiday => 'badge-light-warning',
            self::Maintenance => 'badge-light-info',
            self::PrivateEvent => 'badge-light-primary',
            self::TemporaryClosure => 'badge-light-danger',
            self::Other => 'badge-light-dark',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])
            ->all();
    }
}
