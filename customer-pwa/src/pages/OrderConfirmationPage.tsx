import { useEffect, useMemo, useState } from 'react';
import { Link, useLocation, useParams } from 'react-router-dom';
import { fetchOrder } from '../api/orders';
import { ApiError } from '../api/client';
import { CheckoutItemCard } from '../components/checkout/CheckoutItemCard';
import { PaymentInstructionsCard } from '../components/checkout/PaymentInstructionsCard';
import { EmptyState } from '../components/common/EmptyState';
import { ErrorState } from '../components/common/ErrorState';
import { LoadingSkeleton } from '../components/common/LoadingSkeleton';
import { PageHeader } from '../components/common/PageHeader';
import { OrderStatusBadge } from '../components/orders/OrderStatusBadge';
import { CheckoutPaymentInstructions } from '../types/checkout';
import { Order, OrderPaymentInstructions } from '../types/order';
import { formatCurrency, joinLabels } from '../utils/format';

interface ConfirmationLocationState {
  order?: Order;
  payment?: CheckoutPaymentInstructions;
}

function getPaymentCacheKey(orderId: string): string {
  return `coffee:pwa:payment:${orderId}`;
}

function readCachedPayment(orderId: string): OrderPaymentInstructions | null {
  try {
    const value = window.sessionStorage.getItem(getPaymentCacheKey(orderId));

    return value ? (JSON.parse(value) as OrderPaymentInstructions) : null;
  } catch {
    return null;
  }
}

function writeCachedPayment(orderId: string, payment: OrderPaymentInstructions): void {
  try {
    window.sessionStorage.setItem(getPaymentCacheKey(orderId), JSON.stringify(payment));
  } catch {
    // Best-effort cache for confirmation payment metadata only.
  }
}

export function OrderConfirmationPage() {
  const { orderId = '' } = useParams();
  const location = useLocation();
  const locationState = location.state as ConfirmationLocationState | null;
  const [order, setOrder] = useState<Order | null>(locationState?.order ?? null);
  const [payment, setPayment] = useState<OrderPaymentInstructions | null>(
    locationState?.payment ?? readCachedPayment(orderId),
  );
  const [isLoading, setIsLoading] = useState(order === null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  useEffect(() => {
    if (locationState?.payment && orderId) {
      writeCachedPayment(orderId, locationState.payment);
    }
  }, [locationState?.payment, orderId]);

  useEffect(() => {
    if (!orderId) {
      return;
    }

    if (order && payment) {
      return;
    }

    async function loadOrder(): Promise<void> {
      if (!order) {
        setIsLoading(true);
      }

      setErrorMessage(null);

      try {
        const response = await fetchOrder(orderId);
        setOrder(response.data);

        if (response.meta?.payment) {
          setPayment(response.meta.payment);
          writeCachedPayment(orderId, response.meta.payment);
        }
      } catch (error) {
        if (!order) {
          setErrorMessage(error instanceof ApiError ? error.message : 'Unable to load your order confirmation.');
        }
      } finally {
        setIsLoading(false);
      }
    }

    void loadOrder();
  }, [order, orderId, payment]);

  const statusLabel = useMemo(() => order?.status_label ?? 'Pending Payment', [order]);

  if (isLoading) {
    return (
      <div className="page-container">
        <PageHeader title="Order placed" description="Loading your confirmation…" showBack />
        <LoadingSkeleton cardCount={2} lines={4} />
      </div>
    );
  }

  if (errorMessage) {
    return (
      <div className="page-container">
        <PageHeader title="Order placed" description="We couldn’t load this confirmation." showBack />
        <ErrorState description={errorMessage} onRetry={() => window.location.reload()} />
      </div>
    );
  }

  if (!order) {
    return (
      <div className="page-container">
        <PageHeader title="Order placed" showBack />
        <EmptyState
          title="Confirmation not available"
          description="Open My Orders to find your latest pickup order."
          actionLabel="My Orders"
          actionHref="/orders"
        />
      </div>
    );
  }

  return (
    <div className="page-container checkout-page">
      <PageHeader title="Order placed" description="Next step: complete payment." showBack={false} />

      <section className="confirmation-success motion-enter" aria-live="polite">
        <span className="confirmation-success-icon" aria-hidden="true">
          <i className="bi bi-check-lg"></i>
        </span>
        <p className="eyebrow">Thank you</p>
        <h1>Order placed</h1>
        <p className="confirmation-order-number">{order.order_number}</p>
        <p className="confirmation-total">Total due {formatCurrency(order.total_amount)}</p>
        <OrderStatusBadge status={order.status} label={statusLabel} />
        <p className="confirmation-next-step">Pay now, then share your screenshot so the cafe can start preparing.</p>
      </section>

      <PaymentInstructionsCard
        order={order}
        payment={payment}
        secondaryHref={`/orders/${order.id}`}
        secondaryLabel="Track order"
        onOrderUpdated={setOrder}
      />

      <section className="account-section">
        <div className="account-section-heading">
          <div>
            <span className="auth-badge">Summary</span>
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
            <span>Total due</span>
            <strong>{formatCurrency(order.total_amount)}</strong>
          </div>
        </div>
      </section>

      <div className="confirmation-footer-actions">
        <Link to="/menu" className="btn btn-outline-dark btn-lg rounded-pill w-100">
          Continue shopping
        </Link>
      </div>
    </div>
  );
}
