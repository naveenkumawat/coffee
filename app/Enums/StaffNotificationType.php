<?php

namespace App\Enums;

enum StaffNotificationType: string
{
    case OrderPlaced = 'staff_order_placed';
    case PaymentProofReceived = 'staff_payment_proof_received';
    case PaymentProofResubmitted = 'staff_payment_proof_resubmitted';
    case PaymentConfirmed = 'staff_payment_confirmed';
    case OrderAccepted = 'staff_order_accepted';
    case OrderCancelled = 'staff_order_cancelled';
    case OrderRejected = 'staff_order_rejected';
    case IngredientLowStock = 'staff_ingredient_low_stock';
    case IngredientOutOfStock = 'staff_ingredient_out_of_stock';
    case IngredientStockRestored = 'staff_ingredient_stock_restored';
    case RefillRequestCreated = 'staff_refill_request_created';
    case RefillRequestApproved = 'staff_refill_request_approved';
    case RefillRequestRejected = 'staff_refill_request_rejected';
    case RefillRequestCompleted = 'staff_refill_request_completed';

    public function label(): string
    {
        return match ($this) {
            self::OrderPlaced => 'New order',
            self::PaymentProofReceived => 'Payment proof needs review',
            self::PaymentProofResubmitted => 'Payment proof resubmitted',
            self::PaymentConfirmed => 'Order ready to accept',
            self::OrderAccepted => 'Order accepted',
            self::OrderCancelled => 'Order cancelled',
            self::OrderRejected => 'Order rejected',
            self::IngredientLowStock => 'Low stock',
            self::IngredientOutOfStock => 'Out of stock',
            self::IngredientStockRestored => 'Stock restored',
            self::RefillRequestCreated => 'Refill request',
            self::RefillRequestApproved => 'Refill approved',
            self::RefillRequestRejected => 'Refill rejected',
            self::RefillRequestCompleted => 'Refill completed',
        };
    }

    public function severity(): StaffNotificationSeverity
    {
        return match ($this) {
            self::OrderPlaced,
            self::PaymentProofReceived,
            self::PaymentProofResubmitted,
            self::IngredientLowStock,
            self::RefillRequestCreated,
            self::RefillRequestApproved => StaffNotificationSeverity::Warning,
            self::OrderCancelled,
            self::OrderRejected,
            self::IngredientOutOfStock,
            self::RefillRequestRejected => StaffNotificationSeverity::Critical,
            self::PaymentConfirmed,
            self::OrderAccepted,
            self::IngredientStockRestored,
            self::RefillRequestCompleted => StaffNotificationSeverity::Success,
        };
    }
}
