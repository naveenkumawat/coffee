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
  canSubmitTransaction: boolean;
  primaryAction: 'submit_transaction' | 'replace_transaction' | 'track_order' | 'none';
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

  if (order.payment_status === 'awaiting_review' && Boolean(order.payment_proof?.uploaded || order.payment_transaction_id)) {
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
  const canSubmit = Boolean(proof?.can_submit_transaction ?? proof?.can_upload ?? isPendingPayment(order.status));

  switch (state) {
    case 'cash_confirmed':
      return {
        state,
        badge: 'Paid · Cash',
        title: 'Cash received',
        body: 'The cafe has marked your cash payment as received.',
        canUploadProof: false,
        canSubmitTransaction: false,
        primaryAction: 'track_order',
      };
    case 'cash_pending':
      return {
        state,
        badge: isDineInOrder(order) ? 'Cash Pending' : 'Cash at Pickup',
        title: isDineInOrder(order) ? 'Pay at the cafe' : 'Pay when collecting',
        body: isDineInOrder(order)
          ? `Pay ${amount} in cash at your table / the cafe. No Transaction ID is needed.`
          : `Your order has been placed. Pay ${amount} in cash when you collect it.`,
        canUploadProof: false,
        canSubmitTransaction: false,
        primaryAction: 'track_order',
      };
    case 'upi_awaiting_review':
      return {
        state,
        badge: 'Payment verification pending',
        title: 'Payment verification pending',
        body: "We've received your transaction ID. Your order will be confirmed once the payment is verified.",
        canUploadProof: canSubmit,
        canSubmitTransaction: canSubmit,
        primaryAction: canSubmit ? 'replace_transaction' : 'track_order',
      };
    case 'upi_rejected':
      return {
        state,
        badge: 'Verification Needed',
        title: 'Transaction ID needs another look',
        body:
          order.payment_proof?.rejection_notes?.trim() ||
          'We could not verify that Transaction ID. Please check it in your payment app and submit again.',
        canUploadProof: canSubmit,
        canSubmitTransaction: canSubmit,
        primaryAction: 'replace_transaction',
      };
    case 'upi_confirmed':
      return {
        state,
        badge: 'Payment Confirmed',
        title: 'Payment confirmed',
        body: 'Payment confirmed. Track your order for preparation updates.',
        canUploadProof: false,
        canSubmitTransaction: false,
        primaryAction: 'track_order',
      };
    case 'upi_pending':
    default:
      return {
        state,
        badge: 'UPI Pending',
        title: 'Pay via UPI',
        body: `Pay ${amount} via UPI / QR, then enter your Transaction ID / UTR so we can verify payment.`,
        canUploadProof: canSubmit,
        canSubmitTransaction: canSubmit,
        primaryAction: 'submit_transaction',
      };
  }
}
