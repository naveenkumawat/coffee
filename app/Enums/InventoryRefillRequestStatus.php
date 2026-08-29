<?php

namespace App\Enums;

enum InventoryRefillRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Completed => 'Completed',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'badge-light-warning',
            self::Approved => 'badge-light-primary',
            self::Rejected => 'badge-light-danger',
            self::Completed => 'badge-light-success',
        };
    }

    public function isActive(): bool
    {
        return match ($this) {
            self::Pending, self::Approved => true,
            self::Rejected, self::Completed => false,
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])
            ->all();
    }
}
