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
import { OrderStatusTimeline } from '../components/orders/OrderStatusTimeline';
import { Order, OrderPaymentInstructions } from '../types/order';
import { formatCurrency, formatDateTime, joinLabels } from '../utils/format';
import { isPendingPayment } from '../utils/orders';

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
        <PageHeader title="Order detail" description="Refreshing live order status from the server." showBack />
        <LoadingSkeleton cardCount={3} lines={4} />
      </div>
    );
  }

  if (errorMessage) {
    return (
      <div className="page-container">
        <PageHeader title="Order detail" description="Refreshing live order status from the server." showBack />
        <ErrorState description={errorMessage} onRetry={() => void loadOrder()} />
      </div>
    );
  }

  if (!order) {
    return (
      <div className="page-container">
        <PageHeader title="Order detail" description="Refreshing live order status from the server." showBack />
        <EmptyState
          title="Order not found"
          description="We couldn’t find that order in your account."
          actionLabel="My Orders"
          actionHref="/orders"
        />
      </div>
    );
  }

  return (
    <div className="page-container checkout-page">
      <PageHeader
        title="Order detail"
        description="Server-authoritative status, totals, and payment guidance."
        showBack
        rightSlot={
          <button type="button" className="link-button" onClick={() => void loadOrder()}>
            Refresh
          </button>
        }
      />

      <section className="account-hero">
        <span className="account-hero-badge">{order.status_label ?? 'Order'}</span>
        <h2>Order {order.order_number}</h2>
        <p>Placed {formatDateTime(order.placed_at)}</p>
        <p>Total {formatCurrency(order.total_amount)}</p>
      </section>

      <OrderStatusTimeline order={order} />

      {isPendingPayment(order.status) ? (
        <PaymentInstructionsCard order={order} payment={payment} />
      ) : null}

      <section className="account-section">
        <div className="account-section-heading">
          <div>
            <span className="auth-badge">Items</span>
            <h2>What you ordered</h2>
            <p>Product, variant, quantity, and line totals from the server snapshot.</p>
          </div>
        </div>

        <div className="checkout-list">
          {order.items.map((item) => (
            <CheckoutItemCard
              key={item.id}
              name={item.product_name}
              subtitle={joinLabels([item.variant_name, `${formatCurrency(item.unit_price)} each`])}
              detail={item.customer_ingredient_summary}
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
          <div>
            <span>Total</span>
            <strong>{formatCurrency(order.total_amount)}</strong>
          </div>
        </div>
      </section>

      <section className="account-section">
        <div className="account-section-heading">
          <div>
            <span className="auth-badge">Pickup</span>
            <h2>Collection details</h2>
            <p>Contact details captured at checkout for this order.</p>
          </div>
        </div>
        <div className="order-meta-grid">
          <div>
            <span>Pickup name</span>
            <strong>{order.pickup_name}</strong>
          </div>
          <div>
            <span>Pickup phone</span>
            <strong>{order.pickup_phone}</strong>
          </div>
          {order.customer_notes ? (
            <div>
              <span>Customer notes</span>
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
        <span>Looking for another order?</span>
        <Link to="/orders">Back to My Orders</Link>
      </div>
    </div>
  );
}
