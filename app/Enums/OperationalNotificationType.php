<?php

namespace App\Enums;

/**
 * R1.3A operational notification type keys (persisted on operational_notifications.type).
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
}
