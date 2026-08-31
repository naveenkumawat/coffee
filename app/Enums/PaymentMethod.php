<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Manual = 'manual';
    case Cash = 'cash';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'UPI / QR',
            self::Cash => 'Cash',
        };
    }

    /**
     * Customer-facing / API key (stable contract).
     */
    public function apiKey(): string
    {
        return match ($this) {
            self::Manual => 'manual_upi',
            self::Cash => 'cash',
        };
    }

    public function customerLabel(?OrderFulfilmentMethod $fulfilment = null): string
    {
        return match ($this) {
            self::Manual => 'UPI / QR',
            self::Cash => match ($fulfilment) {
                OrderFulfilmentMethod::Takeaway => 'Cash at Pickup',
                OrderFulfilmentMethod::DineIn => 'Cash',
                default => 'Cash',
            },
        };
    }

    public function customerSubtitle(?OrderFulfilmentMethod $fulfilment = null): string
    {
        return match ($this) {
            self::Manual => 'Pay now and submit payment proof.',
            self::Cash => match ($fulfilment) {
                OrderFulfilmentMethod::Takeaway => 'Pay cash when you collect your order.',
                OrderFulfilmentMethod::DineIn => 'Pay at the cafe.',
                default => 'Pay in cash.',
            },
        };
    }

    public function isCash(): bool
    {
        return $this === self::Cash;
    }

    public function requiresPaymentProof(): bool
    {
        return $this === self::Manual;
    }

    public static function tryFromApiKey(?string $key): ?self
    {
        $key = trim((string) $key);

        return match ($key) {
            'manual_upi', 'manual' => self::Manual,
            'cash' => self::Cash,
            default => self::tryFrom($key),
        };
    }
}
