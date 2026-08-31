<?php

namespace App\Enums;

enum CustomerNotificationType: string
{
    case Welcome = 'customer_welcome';
    case PasswordReset = 'customer_password_reset';
    case PasswordChanged = 'customer_password_changed';
    case OrderPlaced = 'order_placed';
    case PaymentProofReceived = 'payment_proof_received';
    case PaymentConfirmed = 'payment_confirmed';
    case PaymentProofRejected = 'payment_proof_rejected';
    case OrderAccepted = 'order_accepted';
    case OrderPreparing = 'order_preparing';
    case OrderReady = 'order_ready';
    case OrderCompleted = 'order_completed';
    case OrderCancelled = 'order_cancelled';
    case OrderRejected = 'order_rejected';

    public function label(): string
    {
        return match ($this) {
            self::Welcome => 'Welcome',
            self::PasswordReset => 'Password reset',
            self::PasswordChanged => 'Password changed',
            self::OrderPlaced => 'Order confirmation',
            self::PaymentProofReceived => 'Payment proof received',
            self::PaymentConfirmed => 'Payment confirmed',
            self::PaymentProofRejected => 'Payment proof replacement requested',
            self::OrderAccepted => 'Order accepted',
            self::OrderPreparing => 'Order preparing',
            self::OrderReady => 'Order ready',
            self::OrderCompleted => 'Order completed',
            self::OrderCancelled => 'Order cancelled',
            self::OrderRejected => 'Order rejected',
        };
    }
}
