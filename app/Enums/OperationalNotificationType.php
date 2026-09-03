<?php

namespace App\Enums;

/**
 * Operational notification type keys (persisted on operational_notifications.type).
 */
enum OperationalNotificationType: string
{
    /** Cash retail order placed — staff may confirm/accept while PendingPayment. */
    case OrderRequiresAttention = 'order.requires_attention';

    /** Retail order payment confirmed — staff must Accept (or Reject/Cancel). */
    case OrderRequiresAcceptance = 'order.requires_acceptance';

    /** UPI proof uploaded — Operator/Admin must verify. */
    case OrderPaymentProofReview = 'order.payment_proof_review';

    /**
     * Station ticket awaiting first claim.
     * Resolves on Accepted (first meaningful station action), not Ready — avoids prep noise.
     */
    case PreparationTicketPending = 'preparation.ticket_pending';

    /** Dining round: all required station tickets Ready. */
    case DiningReadyToServe = 'dining.ready_to_serve';

    case OrderCancelled = 'order.cancelled';
    case OrderRejected = 'order.rejected';
    case PreparationTicketCancelled = 'preparation.ticket_cancelled';
    case DiningRoundCancelled = 'dining.round_cancelled';

    // --- R1.4 customer-facing (order/session owner only) ---

    case CustomerOrderPlaced = 'customer.order.placed';
    case CustomerPaymentProofReceived = 'customer.payment.proof_received';
    case CustomerPaymentConfirmed = 'customer.payment.confirmed';
    case CustomerPaymentRejected = 'customer.payment.rejected';
    case CustomerOrderAccepted = 'customer.order.accepted';
    case CustomerOrderPreparing = 'customer.order.preparing';
    case CustomerOrderReady = 'customer.order.ready';
    case CustomerOrderCompleted = 'customer.order.completed';
    case CustomerOrderCancelled = 'customer.order.cancelled';
    case CustomerOrderRejected = 'customer.order.rejected';
    case CustomerDiningRoundUpdated = 'customer.dining.round_updated';
    case CustomerDiningReady = 'customer.dining.ready';
    case CustomerDiningBillRequested = 'customer.dining.bill_requested';
    case CustomerDiningSessionClosed = 'customer.dining.session_closed';

    public function isCustomerFacing(): bool
    {
        return str_starts_with($this->value, 'customer.');
    }
}
