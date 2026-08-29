<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case PaymentConfirmed = 'payment_confirmed';
    case Accepted = 'accepted';
    case Preparing = 'preparing';
    case ReadyForPickup = 'ready_for_pickup';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Pending Payment',
            self::PaymentConfirmed => 'Payment Confirmed',
            self::Accepted => 'Accepted',
            self::Preparing => 'Preparing',
            self::ReadyForPickup => 'Ready for Pickup',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::Rejected => 'Rejected',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::PendingPayment => 'badge-light-warning',
            self::PaymentConfirmed => 'badge-light-primary',
            self::Accepted => 'badge-light-info',
            self::Preparing => 'badge-light-dark',
            self::ReadyForPickup => 'badge-light-success',
            self::Completed => 'badge-light-success',
            self::Cancelled, self::Rejected => 'badge-light-danger',
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::Cancelled, self::Rejected => true,
            self::PendingPayment, self::PaymentConfirmed, self::Accepted, self::Preparing, self::ReadyForPickup => false,
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])
            ->all();
    }
}
