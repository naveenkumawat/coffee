<?php

namespace App\Enums;

enum DiningSessionStatus: string
{
    case Open = 'open';
    case BillingRequested = 'billing_requested';
    case AwaitingPayment = 'awaiting_payment';
    case Paid = 'paid';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::BillingRequested => 'Bill Requested',
            self::AwaitingPayment => 'Awaiting Payment',
            self::Paid => 'Paid',
            self::Closed => 'Closed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isActive(): bool
    {
        return match ($this) {
            self::Open, self::BillingRequested, self::AwaitingPayment, self::Paid => true,
            self::Closed, self::Cancelled => false,
        };
    }

    public function occupiesTable(): bool
    {
        return match ($this) {
            self::Open, self::BillingRequested, self::AwaitingPayment, self::Paid => true,
            self::Closed, self::Cancelled => false,
        };
    }

    public function allowsNewRounds(): bool
    {
        return $this === self::Open;
    }

    public function tableOperationalState(): string
    {
        return match ($this) {
            self::Open => 'occupied',
            self::BillingRequested => 'bill_requested',
            self::AwaitingPayment, self::Paid => 'awaiting_payment',
            self::Closed, self::Cancelled => 'available',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Open => 'badge-light-warning',
            self::BillingRequested => 'badge-light-info',
            self::AwaitingPayment => 'badge-light-primary',
            self::Paid => 'badge-light-success',
            self::Closed => 'badge-light-dark',
            self::Cancelled => 'badge-light-danger',
        };
    }
}
