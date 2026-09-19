import { Link } from 'react-router-dom';
import { Order } from '../../types/order';
import { formatCurrency, formatDateTime } from '../../utils/format';
import {
  customerWorkflowStatusLabel,
  isActiveOrder,
  isDeliveryOrder,
  isDineInOrder,
  isPendingPayment,
  isReadyForPickup,
  orderListActionLabel,
  primaryItemLabel,
  statusTone,
} from '../../utils/orders';
import { paymentStatePresentation } from '../../utils/paymentState';
import { OrderStatusBadge } from './OrderStatusBadge';

interface OrderListCardProps {
  order: Order;
}

export function OrderListCard({ order }: OrderListCardProps) {
  const tone = statusTone(order.status);
  const active = isActiveOrder(order.status);
  const actionLabel = orderListActionLabel(order);
  const delivery = isDeliveryOrder(order);
  const dineIn = isDineInOrder(order);

  const payment = paymentStatePresentation(order);
  const awaitingReview = payment.state === 'upi_awaiting_review';
  const paymentConfirmed = payment.state === 'upi_confirmed' || payment.state === 'cash_confirmed';

  return (
    <Link
      to={`/orders/${order.id}`}
      className={[
        'order-list-card',
        `is-${tone}`,
        active ? 'is-active-order' : 'is-quiet-order',
        isPendingPayment(order.status) && !awaitingReview && !paymentConfirmed ? 'needs-payment' : '',
        isReadyForPickup(order.status) ? 'is-ready' : '',
      ]
        .filter(Boolean)
        .join(' ')}
    >
      <div className="order-list-card-top">
        <div className="order-list-card-copy">
          <OrderStatusBadge status={order.status} label={customerWorkflowStatusLabel(order)} />
          <OrderStatusBadge
            status={paymentConfirmed ? 'payment_confirmed' : 'pending_payment'}
            label={payment.badge}
          />
          <h2>{order.order_number}</h2>
          <p className="order-list-date">
            {formatDateTime(order.placed_at)}
            {order.fulfilment_method_label ? ` · ${order.fulfilment_method_label}` : ''}
            {dineIn && order.table_name ? ` · ${order.table_name}` : ''}
          </p>
        </div>
        <strong className="order-list-total">{formatCurrency(order.total_amount)}</strong>
      </div>

      <div className="order-list-card-bottom">
        <p className="order-list-items">{primaryItemLabel(order)}</p>
        <span className="order-list-action">
          {actionLabel}
          <i className="bi bi-chevron-right" aria-hidden="true"></i>
        </span>
      </div>

      {awaitingReview ? (
        <p className="order-list-callout">Payment verification pending</p>
      ) : isPendingPayment(order.status) && !paymentConfirmed ? (
        <p className="order-list-callout">Payment needed to start preparation</p>
      ) : null}
      {isReadyForPickup(order.status) ? (
        <p className="order-list-callout is-ready-callout">
          {delivery
            ? 'Ready for delivery handover — third-party charges are separate'
            : dineIn
              ? `Ready to serve${order.table_name ? ` · ${order.table_name}` : ''}`
              : 'Ready at the cafe — come pick it up'}
        </p>
      ) : null}
    </Link>
  );
}
