import { Order } from '../types/order';
import { formatCurrency } from './format';
import { isCashPayment, isDineInOrder, isPendingPayment } from './orders';

export type CanonicalPaymentState =
  | 'cash_pending'
  | 'cash_confirmed'
  | 'upi_pending'
  | 'upi_awaiting_review'
  | 'upi_rejected'
  | 'upi_confirmed';

export interface PaymentStatePresentation {
  state: CanonicalPaymentState;
  badge: string;
  title: string;
  body: string;
  canUploadProof: boolean;
  primaryAction: 'upload_proof' | 'replace_proof' | 'track_order' | 'none';
}

/**
 * Canonical payment-state wording for confirmation, order detail, and shared payment cards.
 * Server payment_status remains the authority — this only maps it for UI.
 */
export function resolvePaymentState(order: Order): CanonicalPaymentState {
  if (isCashPayment(order)) {
    if (order.payment_status === 'confirmed' || Boolean(order.cash_received_at)) {
      return 'cash_confirmed';
    }

    return 'cash_pending';
  }

  if (order.payment_status === 'awaiting_review' && Boolean(order.payment_proof?.uploaded)) {
    return 'upi_awaiting_review';
  }

  if (order.payment_status === 'rejected') {
    return 'upi_rejected';
  }

  if (
    order.payment_status === 'confirmed' ||
    (order.status !== null &&
      !isPendingPayment(order.status) &&
      order.payment_status !== 'awaiting_review' &&
      order.payment_status !== 'rejected' &&
      order.payment_status !== 'pending')
  ) {
    return 'upi_confirmed';
  }

  return 'upi_pending';
}

export function paymentStatePresentation(order: Order): PaymentStatePresentation {
  const state = resolvePaymentState(order);
  const proof = order.payment_proof;
  const amount = formatCurrency(order.total_amount);

  switch (state) {
    case 'cash_confirmed':
      return {
        state,
        badge: 'Paid · Cash',
        title: 'Cash received',
        body: 'The cafe has marked your cash payment as received.',
        canUploadProof: false,
        primaryAction: 'track_order',
      };
    case 'cash_pending':
      return {
        state,
        badge: isDineInOrder(order) ? 'Cash Pending' : 'Cash at Pickup',
        title: isDineInOrder(order) ? 'Pay at the cafe' : 'Pay when collecting',
        body: isDineInOrder(order)
          ? `Pay ${amount} in cash at your table / the cafe. No payment screenshot is needed.`
          : `Your order has been placed. Pay ${amount} in cash when you collect it.`,
        canUploadProof: false,
        primaryAction: 'track_order',
      };
    case 'upi_awaiting_review':
      return {
        state,
        badge: 'Awaiting Verification',
        title: 'Proof submitted',
        body: 'Payment proof submitted. Waiting for cafe confirmation.',
        canUploadProof: proof?.can_upload === true,
        primaryAction: proof?.can_upload === true ? 'replace_proof' : 'track_order',
      };
    case 'upi_rejected':
      return {
        state,
        badge: 'Verification Needed',
        title: 'Proof needs another look',
        body:
          order.payment_proof?.rejection_notes?.trim() ||
          'Please upload a clearer payment screenshot so we can start preparing.',
        canUploadProof: Boolean(proof?.can_upload ?? true),
        primaryAction: 'replace_proof',
      };
    case 'upi_confirmed':
      return {
        state,
        badge: 'Payment Confirmed',
        title: 'Payment confirmed',
        body: 'Payment confirmed. Track your order for preparation updates.',
        canUploadProof: false,
        primaryAction: 'track_order',
      };
    case 'upi_pending':
    default:
      return {
        state,
        badge: 'UPI Pending',
        title: 'Pay by UPI',
        body: `Pay ${amount} and upload your payment screenshot so we can start preparing.`,
        canUploadProof: Boolean(proof?.can_upload ?? isPendingPayment(order.status)),
        primaryAction: 'upload_proof',
      };
  }
}
