import { useState } from 'react';
import { Link } from 'react-router-dom';
import { Order } from '../../types/order';
import { formatDateTime } from '../../utils/format';
import {
  isDeliveryOrder,
  isDineInOrder,
  isPendingPayment,
  isReadyForPickup,
  isTerminalFailure,
  preparationStepsForOrder,
  progressStepIndex,
  sortedTimeline,
  timelineTimestampForStatus,
} from '../../utils/orders';
import { resolvePaymentState } from '../../utils/paymentState';

interface OrderStatusTimelineProps {
  order: Order;
}

export function OrderStatusTimeline({ order }: OrderStatusTimelineProps) {
  const currentIndex = progressStepIndex(order.status);
  const failed = isTerminalFailure(order.status);
  const paymentState = resolvePaymentState(order);
  const awaitingReview = paymentState === 'upi_awaiting_review';
  const unpaidUpi = paymentState === 'upi_pending' || paymentState === 'upi_rejected';
  const pendingPayment = isPendingPayment(order.status) && unpaidUpi;
  const ready = isReadyForPickup(order.status);
  const delivery = isDeliveryOrder(order);
  const dineIn = isDineInOrder(order);
  const steps = preparationStepsForOrder(order);
  const history = sortedTimeline(order.status_timeline);
  const [historyOpen, setHistoryOpen] = useState(false);

  if (failed) {
    return (
      <section className="account-section order-tracking-section">
        <div className="order-terminal-banner motion-enter">
          <strong>{order.status_label ?? 'Order closed'}</strong>
          <p>
            {order.status === 'rejected'
              ? 'The cafe could not accept this order. Message the cafe if you need help placing a new one.'
              : 'This order was cancelled. No further preparation steps apply.'}
          </p>
        </div>

        {history.length > 0 ? (
          <details className="order-history-details">
            <summary>Status history</summary>
            <ul className="order-history-list">
              {history.map((entry) => (
                <li key={entry.id}>
                  <strong>{entry.to_status_label ?? entry.to_status ?? 'Updated'}</strong>
                  <span>{formatDateTime(entry.created_at)}</span>
                </li>
              ))}
            </ul>
          </details>
        ) : null}
      </section>
    );
  }

  return (
    <section className="account-section order-tracking-section">
      <div className="account-section-heading">
        <div>
          <span className="auth-badge">Tracking</span>
          <h2>Order progress</h2>
          <p>
            {awaitingReview
              ? "We've received your transaction ID. Preparation starts after the cafe verifies payment."
              : pendingPayment
              ? 'Preparation starts after the cafe confirms your payment.'
              : ready
                ? delivery
                  ? 'Your order is ready for the delivery partner.'
                  : dineIn
                    ? 'Your order is ready to serve at your table.'
                    : 'Your drinks are ready — head to the cafe when you can.'
                : 'Live updates from the cafe team. Refresh anytime.'}
          </p>
        </div>
      </div>

      {awaitingReview ? (
        <div className="order-next-step is-payment">
          <strong>Payment verification pending</strong>
          <p>
            We&apos;ve received your transaction ID. You can submit another one only if the café rejects this
            payment.
          </p>
        </div>
      ) : null}

      {pendingPayment ? (
        <div className="order-next-step is-payment">
          <strong>{paymentState === 'upi_rejected' ? 'Transaction ID not verified' : 'Awaiting payment'}</strong>
          <p>
            {paymentState === 'upi_rejected'
              ? 'The café could not verify the previous Transaction ID. Submit a new one to continue.'
              : 'Pay via UPI and enter your Transaction ID / UTR so the cafe can confirm and start preparing.'}
          </p>
        </div>
      ) : null}

      {ready ? (
        <div className="order-next-step is-ready motion-enter">
          <strong>
            {delivery ? 'Ready for Delivery' : dineIn ? 'Ready to Serve' : 'Ready for Pickup'}
          </strong>
          <p>
            {delivery
              ? 'The cafe will hand this order to a third-party delivery service. Delivery charges are paid separately.'
              : dineIn
                ? `We’ll bring this to ${order.table_name ? `table ${order.table_name}` : 'your table'}.`
                : 'Show your order number at the counter. Enjoy!'}
          </p>
        </div>
      ) : null}

      <ol className="order-progress-list" aria-label="Preparation progress">
        {steps.map((step, index) => {
          const isComplete = currentIndex > index;
          const isCurrent = currentIndex === index;
          const isFuture = currentIndex < index;
          const timestamp =
            timelineTimestampForStatus(order, step.status) ??
            history.find((entry) => entry.to_status === step.status)?.created_at ??
            null;

          return (
            <li
              key={step.status}
              className={[
                'order-progress-item',
                isComplete ? 'is-complete' : '',
                isCurrent ? 'is-current' : '',
                isFuture || pendingPayment ? 'is-future' : '',
              ]
                .filter(Boolean)
                .join(' ')}
            >
              <span className="order-progress-marker" aria-hidden="true">
                {isComplete ? <i className="bi bi-check-lg"></i> : index + 1}
              </span>
              <div>
                <strong>{step.label}</strong>
                <p>
                  {pendingPayment
                    ? 'After payment'
                    : isCurrent
                      ? 'Current step'
                      : timestamp
                        ? formatDateTime(timestamp)
                        : isComplete
                          ? 'Done'
                          : 'Up next'}
                </p>
              </div>
            </li>
          );
        })}
      </ol>

      {history.length > 0 ? (
        <div className="order-history-block">
          <button
            type="button"
            className="link-button order-history-toggle"
            aria-expanded={historyOpen}
            onClick={() => setHistoryOpen((open) => !open)}
          >
            {historyOpen ? 'Hide status history' : 'Show status history'}
          </button>
          {historyOpen ? (
            <ul className="order-history-list">
              {history.map((entry) => (
                <li key={entry.id}>
                  <strong>{entry.to_status_label ?? entry.to_status ?? 'Updated'}</strong>
                  <span>{formatDateTime(entry.created_at)}</span>
                </li>
              ))}
            </ul>
          ) : null}
        </div>
      ) : null}
    </section>
  );
}
