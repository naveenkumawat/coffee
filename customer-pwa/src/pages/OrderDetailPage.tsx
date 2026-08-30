import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { fetchOrder } from '../api/orders';
import { ApiError } from '../api/client';
import { CheckoutItemCard } from '../components/checkout/CheckoutItemCard';
import { PaymentInstructionsCard } from '../components/checkout/PaymentInstructionsCard';
import { EmptyState } from '../components/common/EmptyState';
import { ErrorState } from '../components/common/ErrorState';
import { LoadingSkeleton } from '../components/common/LoadingSkeleton';
import { PageHeader } from '../components/common/PageHeader';
import { OrderStatusBadge } from '../components/orders/OrderStatusBadge';
import { OrderStatusTimeline } from '../components/orders/OrderStatusTimeline';
import { Order, OrderPaymentInstructions } from '../types/order';
import { formatCurrency, formatDateTime, joinLabels } from '../utils/format';
import { isPendingPayment, statusTone } from '../utils/orders';

export function OrderDetailPage() {
  const { orderId = '' } = useParams();
  const [order, setOrder] = useState<Order | null>(null);
  const [payment, setPayment] = useState<OrderPaymentInstructions | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  async function loadOrder(): Promise<void> {
    if (!orderId) {
      setErrorMessage('Order not found.');
      setIsLoading(false);
      return;
    }

    setIsLoading(true);
    setErrorMessage(null);

    try {
      const response = await fetchOrder(orderId);
      setOrder(response.data);
      setPayment(response.meta?.payment ?? null);
    } catch (error) {
      setOrder(null);
      setPayment(null);
      setErrorMessage(error instanceof ApiError ? error.message : 'Unable to load this order.');
    } finally {
      setIsLoading(false);
    }
  }

  useEffect(() => {
    void loadOrder();
  }, [orderId]);

  if (isLoading) {
    return (
      <div className="page-container">
        <PageHeader title="Order" description="Loading status…" showBack />
        <LoadingSkeleton cardCount={3} lines={4} variant="list" />
      </div>
    );
  }

  if (errorMessage) {
    return (
      <div className="page-container">
        <PageHeader title="Order" description="Track your pickup order." showBack />
        <ErrorState description={errorMessage} onRetry={() => void loadOrder()} />
      </div>
    );
  }

  if (!order) {
    return (
      <div className="page-container">
        <PageHeader title="Order" description="Track your pickup order." showBack />
        <EmptyState
          title="Order not found"
          description="We couldn’t find that order in your account."
          actionLabel="My Orders"
          actionHref="/orders"
        />
      </div>
    );
  }

  const tone = statusTone(order.status);
  const pendingPayment = isPendingPayment(order.status);

  return (
    <div className="page-container order-detail-page">
      <PageHeader
        title="Order"
        description="Status, payment, and pickup details"
        showBack
        rightSlot={
          <button type="button" className="link-button" onClick={() => void loadOrder()}>
            Refresh
          </button>
        }
      />

      <section className={`order-status-hero is-${tone} motion-enter`}>
        <OrderStatusBadge status={order.status} label={order.status_label} />
        <h1 className="order-status-number">{order.order_number}</h1>
        <p className="order-status-total">Total {formatCurrency(order.total_amount)}</p>
        <p className="order-status-meta">Placed {formatDateTime(order.placed_at)}</p>
      </section>

      {pendingPayment ? (
        <PaymentInstructionsCard order={order} payment={payment} showSecondaryAction={false} />
      ) : null}

      <OrderStatusTimeline order={order} />

      <section className="account-section">
        <div className="account-section-heading">
          <div>
            <span className="auth-badge">Items</span>
            <h2>What you ordered</h2>
          </div>
        </div>

        <div className="checkout-list">
          {order.items.map((item) => (
            <CheckoutItemCard
              key={item.id}
              name={item.product_name}
              subtitle={joinLabels([item.variant_name, item.customer_ingredient_summary])}
              quantity={item.quantity}
              amount={item.line_subtotal}
            />
          ))}
        </div>

        <div className="summary-card checkout-summary-grid">
          <div>
            <span>Subtotal</span>
            <strong>{formatCurrency(order.subtotal)}</strong>
          </div>
          <div className="cart-summary-total">
            <span>Total</span>
            <strong>{formatCurrency(order.total_amount)}</strong>
          </div>
        </div>
      </section>

      <section className="account-section order-pickup-section">
        <div className="account-section-heading">
          <div>
            <span className="auth-badge">Pickup</span>
            <h2>Collection details</h2>
          </div>
        </div>
        <div className="order-meta-grid">
          <div>
            <span>Name</span>
            <strong>{order.pickup_name}</strong>
          </div>
          <div>
            <span>Phone</span>
            <strong>{order.pickup_phone}</strong>
          </div>
          {order.customer_notes ? (
            <div>
              <span>Notes for cafe</span>
              <strong>{order.customer_notes}</strong>
            </div>
          ) : null}
          {order.pickup_notes ? (
            <div>
              <span>Pickup notes</span>
              <strong>{order.pickup_notes}</strong>
            </div>
          ) : null}
        </div>
      </section>

      <div className="page-note">
        <span>Need another order?</span>
        <Link to="/orders">Back to My Orders</Link>
      </div>
    </div>
  );
}
