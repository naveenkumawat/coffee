<?php

namespace App\Enums;

enum OrderPreparationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Accepted => 'Accepted',
            self::Preparing => 'Preparing',
            self::Ready => 'Ready',
            self::Cancelled => 'Cancelled',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'badge-light-warning',
            self::Accepted => 'badge-light-info',
            self::Preparing => 'badge-light-dark',
            self::Ready => 'badge-light-success',
            self::Cancelled => 'badge-light-danger',
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Ready, self::Cancelled => true,
            default => false,
        };
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Accepted, self::Cancelled],
            self::Accepted => [self::Preparing, self::Cancelled],
            self::Preparing => [self::Ready, self::Cancelled],
            self::Ready, self::Cancelled => [],
        };
    }
}
