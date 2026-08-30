import { Link } from 'react-router-dom';
import { Order } from '../../types/order';
import { formatCurrency, formatDateTime } from '../../utils/format';
import { primaryItemLabel } from '../../utils/orders';

interface OrderListCardProps {
  order: Order;
}

export function OrderListCard({ order }: OrderListCardProps) {
  return (
    <Link to={`/orders/${order.id}`} className="order-list-card">
      <div className="order-list-card-top">
        <div>
          <span className="auth-badge">{order.status_label ?? 'Order'}</span>
          <h2>Order {order.order_number}</h2>
          <p>{formatDateTime(order.placed_at)}</p>
        </div>
        <strong>{formatCurrency(order.total_amount)}</strong>
      </div>
      <div className="order-list-card-bottom">
        <p>{primaryItemLabel(order)}</p>
        <span>
          Track
          <i className="bi bi-chevron-right" aria-hidden="true"></i>
        </span>
      </div>
    </Link>
  );
}
