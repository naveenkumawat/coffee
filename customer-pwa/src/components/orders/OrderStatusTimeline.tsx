import { Order } from '../../types/order';
import { formatDateTime } from '../../utils/format';
import {
  ORDER_PROGRESS_STEPS,
  isTerminalFailure,
  progressStepIndex,
  sortedTimeline,
  timelineTimestampForStatus
} from '../../utils/orders';

interface OrderStatusTimelineProps {
  order: Order;
}

export function OrderStatusTimeline({ order }: OrderStatusTimelineProps) {
  const currentIndex = progressStepIndex(order.status);
  const failed = isTerminalFailure(order.status);
  const history = sortedTimeline(order.status_timeline);

  return (
    <section className="account-section order-tracking-section">
      <div className="account-section-heading">
        <div>
          <span className="auth-badge">Tracking</span>
          <h2>Order progress</h2>
          <p>
            {failed
              ? `This order is ${order.status_label?.toLowerCase() ?? 'closed'} and is no longer moving through preparation.`
              : 'Live status comes from the cafe team. Refresh this page anytime for the latest update.'}
          </p>
        </div>
      </div>

      {failed ? (
        <div className="order-terminal-banner">
          <strong>{order.status_label}</strong>
          <p>
            {order.status === 'rejected'
              ? 'The cafe could not accept this order. Contact the team if you need help with a replacement.'
              : 'This order was cancelled. Payment instructions no longer apply.'}
          </p>
        </div>
      ) : null}

      <ol className="order-progress-list">
        {ORDER_PROGRESS_STEPS.map((step, index) => {
          const isComplete = !failed && currentIndex > index;
          const isCurrent = !failed && currentIndex === index;
          const reachedBeforeFailure = failed && history.some((entry) => entry.to_status === step.status);
          const timestamp = timelineTimestampForStatus(order, step.status)
            ?? history.find((entry) => entry.to_status === step.status)?.created_at
            ?? null;

          return (
            <li
              key={step.status}
              className={[
                'order-progress-item',
                isComplete || reachedBeforeFailure ? 'is-complete' : '',
                isCurrent ? 'is-current' : '',
                failed && !reachedBeforeFailure ? 'is-muted' : ''
              ].filter(Boolean).join(' ')}
            >
              <span className="order-progress-marker" aria-hidden="true">
                {isComplete || reachedBeforeFailure ? <i className="bi bi-check-lg"></i> : index + 1}
              </span>
              <div>
                <strong>{step.label}</strong>
                <p>
                  {isCurrent
                    ? 'Current status'
                    : timestamp
                      ? formatDateTime(timestamp)
                      : failed
                        ? 'Not reached'
                        : 'Waiting'}
                </p>
              </div>
            </li>
          );
        })}
      </ol>

      {history.length > 0 ? (
        <div className="order-history-block">
          <h3>Status history</h3>
          <ul className="order-history-list">
            {history.map((entry) => (
              <li key={entry.id}>
                <strong>{entry.to_status_label ?? entry.to_status ?? 'Updated'}</strong>
                <span>{formatDateTime(entry.created_at)}</span>
              </li>
            ))}
          </ul>
        </div>
      ) : null}
    </section>
  );
}
