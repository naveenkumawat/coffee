<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Manual = 'manual';
    case Cash = 'cash';
    case Razorpay = 'razorpay';
    case PayU = 'payu';
    case Paytm = 'paytm';
    case PhonePe = 'phonepe';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual UPI / QR Payment',
            self::Cash => 'Cash',
            self::Razorpay => 'Razorpay',
            self::PayU => 'PayU',
            self::Paytm => 'Paytm',
            self::PhonePe => 'PhonePe',
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
            self::Razorpay => 'razorpay',
            self::PayU => 'payu',
            self::Paytm => 'paytm',
            self::PhonePe => 'phonepe',
        };
    }

    public function type(): string
    {
        return $this->isOnline() ? 'online' : 'manual';
    }

    public function isOnline(): bool
    {
        return match ($this) {
            self::Razorpay, self::PayU, self::Paytm, self::PhonePe => true,
            default => false,
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
            self::Razorpay => 'Razorpay',
            self::PayU => 'PayU',
            self::Paytm => 'Paytm',
            self::PhonePe => 'PhonePe',
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
            self::Razorpay, self::PayU, self::Paytm, self::PhonePe => 'Pay securely online.',
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

    public function requiresGatewayInitiation(): bool
    {
        return $this->isOnline();
    }

    /**
     * @return list<self>
     */
    public static function onlineCases(): array
    {
        return [self::Razorpay, self::PayU, self::Paytm, self::PhonePe];
    }

    /**
     * @return list<self>
     */
    public static function manualCases(): array
    {
        return [self::Manual, self::Cash];
    }

    public static function tryFromApiKey(?string $key): ?self
    {
        $key = trim((string) $key);

        return match ($key) {
            'manual_upi', 'manual' => self::Manual,
            'cash' => self::Cash,
            'razorpay' => self::Razorpay,
            'payu' => self::PayU,
            'paytm' => self::Paytm,
            'phonepe' => self::PhonePe,
            default => self::tryFrom($key),
        };
    }
}
