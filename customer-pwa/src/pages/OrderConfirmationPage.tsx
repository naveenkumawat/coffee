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

    return value ? JSON.parse(value) as OrderPaymentInstructions : null;
  } catch {
    return null;
  }
}

function writeCachedPayment(orderId: string, payment: OrderPaymentInstructions): void {
  try {
    window.sessionStorage.setItem(getPaymentCacheKey(orderId), JSON.stringify(payment));
  } catch {
    // Session storage is best-effort only for non-authoritative confirmation metadata.
  }
}

export function OrderConfirmationPage() {
  const { orderId = '' } = useParams();
  const location = useLocation();
  const locationState = location.state as ConfirmationLocationState | null;
  const [order, setOrder] = useState<Order | null>(locationState?.order ?? null);
  const [payment, setPayment] = useState<OrderPaymentInstructions | null>(
    locationState?.payment ?? readCachedPayment(orderId)
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

  const timelineLabel = useMemo(() => {
    return order?.status_label ?? 'Pending Payment';
  }, [order]);

  if (isLoading) {
    return (
      <div className="page-container">
        <PageHeader title="Order confirmation" description="Your order is waiting for manual payment confirmation." showBack />
        <LoadingSkeleton cardCount={3} lines={4} />
      </div>
    );
  }

  if (errorMessage) {
    return (
      <div className="page-container">
        <PageHeader title="Order confirmation" description="Your order is waiting for manual payment confirmation." showBack />
        <ErrorState description={errorMessage} onRetry={() => window.location.reload()} />
      </div>
    );
  }

  if (!order) {
    return (
      <div className="page-container">
        <PageHeader title="Order confirmation" description="Your order is waiting for manual payment confirmation." showBack />
        <EmptyState
          title="Order confirmation not available"
          description="We couldn’t find that customer order. Please open your latest confirmation from checkout or visit My Orders."
          actionLabel="My Orders"
          actionHref="/orders"
        />
      </div>
    );
  }

  return (
    <div className="page-container checkout-page">
      <PageHeader title="Order confirmation" description="Your order has been placed and is waiting for manual payment confirmation." showBack />

      <section className="account-hero">
        <span className="account-hero-badge">{timelineLabel}</span>
        <h2>Order {order.order_number}</h2>
        <p>Total due: {formatCurrency(order.total_amount)}</p>
        <p>We’ll keep this order in `Pending Payment` until the cafe team reviews your payment proof.</p>
      </section>

      <PaymentInstructionsCard
        order={order}
        payment={payment}
        secondaryHref={`/orders/${order.id}`}
        secondaryLabel="Track order"
      />

      <section className="account-section">
        <div className="account-section-heading">
          <div>
            <span className="auth-badge">Final order summary</span>
            <h2>What you ordered</h2>
            <p>These are the server-confirmed order snapshots created at checkout.</p>
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
          <div>
            <span>Total</span>
            <strong>{formatCurrency(order.total_amount)}</strong>
          </div>
        </div>
      </section>

      <div className="page-note">
        <span>Need live tracking?</span>
        <Link to={`/orders/${order.id}`}>Open order detail</Link>
      </div>
    </div>
  );
}
