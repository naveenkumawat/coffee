<?php

namespace App\Enums;

enum PaymentAttemptStatus: string
{
    case Pending = 'pending';
    case RequiresAction = 'requires_action';
    case Submitted = 'submitted';
    case Confirmed = 'confirmed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::RequiresAction => 'Requires action',
            self::Submitted => 'Submitted',
            self::Confirmed => 'Confirmed',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
            self::Expired => 'Expired',
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Confirmed, self::Failed, self::Cancelled, self::Expired => true,
            default => false,
        };
    }
}
