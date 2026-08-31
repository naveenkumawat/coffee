<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case AwaitingReview = 'awaiting_review';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::AwaitingReview => 'Awaiting review',
            self::Confirmed => 'Confirmed',
            self::Rejected => 'Replacement requested',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'badge-light-warning',
            self::AwaitingReview => 'badge-light-info',
            self::Confirmed => 'badge-light-success',
            self::Rejected => 'badge-light-danger',
        };
    }
}
