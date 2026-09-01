import { useEffect, useMemo, useState } from 'react';
import { Link, useLocation, useParams } from 'react-router-dom';
import { fetchOrder } from '../api/orders';
import { ApiError } from '../api/client';
import { CheckoutItemCard } from '../components/checkout/CheckoutItemCard';
import { PaymentInstructionsCard } from '../components/checkout/PaymentInstructionsCard';
import { EmptyState } from '../components/common/EmptyState';
import { ErrorState } from '../components/common/ErrorState';
import { LoadingSkeleton } from '../components/common/LoadingSkeleton';
import { DownloadInvoiceButton } from '../components/orders/DownloadInvoiceButton';
import { OrderStatusBadge } from '../components/orders/OrderStatusBadge';
import { OrderTaxBreakdown } from '../components/orders/OrderTaxBreakdown';
import { CheckoutPaymentInstructions } from '../types/checkout';
import { Order, OrderPaymentInstructions } from '../types/order';
import { orderDiscountLines } from '../utils/discounts';
import { formatCurrency, joinLabels } from '../utils/format';
import {
  fulfilmentChipLabel,
  isCashPayment,
  isDeliveryOrder,
  isDineInOrder,
  isPendingPayment,
} from '../utils/orders';

interface ConfirmationLocationState {
  order?: Order;
  payment?: CheckoutPaymentInstructions | null;
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

function confirmationNextStep(order: Order): string {
  if (isCashPayment(order)) {
    if (order.payment_status === 'confirmed' || order.cash_received_at) {
      return 'Cash received. Track your order for preparation updates.';
    }

    if (isDineInOrder(order)) {
      return `Pay ${formatCurrency(order.total_amount)} in cash at the cafe / table. No payment screenshot needed.`;
    }

    return `Your order has been placed. Pay ${formatCurrency(order.total_amount)} in cash when you collect it.`;
  }

  if (order.payment_status === 'awaiting_review') {
    return 'Payment proof submitted. Waiting for cafe confirmation.';
  }

  if (order.payment_status === 'confirmed' || !isPendingPayment(order.status)) {
    return 'Payment confirmed. Track your order for preparation updates.';
  }

  if (order.payment_status === 'rejected') {
    return 'Please upload a clearer payment screenshot so we can start preparing.';
  }

  if (isDineInOrder(order) && order.table_name?.trim()) {
    return `Pay now and share your payment screenshot so we can start preparing for Table ${order.table_name.trim()}.`;
  }

  if (isDeliveryOrder(order)) {
    return 'Pay now and share your payment screenshot so we can start preparing for delivery.';
  }

  return 'Pay now and share your payment screenshot so we can start preparing your order.';
}

function paymentChipLabel(order: Order): string | null {
  if (!isCashPayment(order)) {
    return null;
  }

  if (order.payment_status === 'confirmed' || order.cash_received_at) {
    return 'Paid · Cash';
  }

  if (isDineInOrder(order)) {
    return 'Cash';
  }

  return 'Cash at Pickup';
}

function fulfilmentContextLabel(order: Order): string | null {
  if (isDineInOrder(order) && order.table_name?.trim()) {
    return `Table ${order.table_name.trim()}`;
  }

  if (isDeliveryOrder(order) && order.delivery_address?.trim()) {
    return order.delivery_address.trim();
  }

  if (!isDeliveryOrder(order) && !isDineInOrder(order)) {
    return 'Pickup';
  }

  return null;
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

    if (order && (payment || isCashPayment(order))) {
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
  const fulfilmentLabel = useMemo(() => fulfilmentChipLabel(order), [order]);
  const cashLabel = useMemo(() => (order ? paymentChipLabel(order) : null), [order]);
  const needsPaymentUi = useMemo(() => {
    if (!order) {
      return false;
    }

    if (isCashPayment(order)) {
      return true;
    }

    return (
      isPendingPayment(order.status) ||
      order.payment_status === 'awaiting_review' ||
      order.payment_status === 'rejected' ||
      order.payment_status === 'pending'
    );
  }, [order]);

  if (isLoading) {
    return (
      <div className="page-container confirmation-page">
        <LoadingSkeleton cardCount={2} lines={4} />
      </div>
    );
  }

  if (errorMessage) {
    return (
      <div className="page-container confirmation-page">
        <ErrorState description={errorMessage} onRetry={() => window.location.reload()} />
      </div>
    );
  }

  if (!order) {
    return (
      <div className="page-container confirmation-page">
        <EmptyState
          title="Confirmation not available"
          description="Open My Orders to find your latest order."
          actionLabel="My Orders"
          actionHref="/orders"
        />
      </div>
    );
  }

  const contextLabel = fulfilmentContextLabel(order);
  const paymentConfirmed =
    order.payment_status === 'confirmed' || (!isPendingPayment(order.status) && order.payment_status !== 'rejected');

  return (
    <div className="page-container confirmation-page">
      <section className="confirmation-hero motion-enter" aria-live="polite">
        <span className="confirmation-success-icon" aria-hidden="true">
          <i className="bi bi-check-lg"></i>
        </span>
        <h1>Order placed</h1>
        <p className="confirmation-order-number">{order.order_number}</p>
        <div className="confirmation-amount-block">
          <p className="confirmation-amount">{formatCurrency(order.total_amount)}</p>
          <p className="confirmation-amount-label">Cafe total</p>
        </div>
        <div className="confirmation-meta-row" aria-label="Order fulfilment and status">
          <span className="status-badge is-neutral fulfilment-badge">{fulfilmentLabel}</span>
          {cashLabel ? <span className="status-badge is-neutral fulfilment-badge">{cashLabel}</span> : null}
          <OrderStatusBadge status={order.status} label={statusLabel} className="confirmation-status-badge" />
        </div>
        {contextLabel ? (
          <p className={isDineInOrder(order) ? 'confirmation-table' : 'confirmation-context'}>
            {isDineInOrder(order) ? contextLabel : isDeliveryOrder(order) ? (
              <span className="checkout-prewrap">{contextLabel}</span>
            ) : (
              contextLabel
            )}
          </p>
        ) : null}
        <p className="confirmation-next-step">{confirmationNextStep(order)}</p>
      </section>

      {needsPaymentUi || paymentConfirmed ? (
        <PaymentInstructionsCard
          order={order}
          payment={payment}
          secondaryHref={`/orders/${order.id}`}
          secondaryLabel="Track order"
          onOrderUpdated={setOrder}
        />
      ) : (
        <div className="confirmation-actions">
          <Link to={`/orders/${order.id}`} className="btn btn-primary btn-lg rounded-pill w-100">
            Track order
          </Link>
        </div>
      )}

      {isDeliveryOrder(order) ? (
        <section className="checkout-section" aria-labelledby="confirmation-delivery-heading">
          <div className="checkout-section-heading">
            <h2 id="confirmation-delivery-heading">Delivery</h2>
          </div>
          <div className="confirmation-detail-list">
            {order.delivery_contact_name ? (
              <div>
                <span>Contact</span>
                <strong>{order.delivery_contact_name}</strong>
              </div>
            ) : null}
            <div>
              <span>Phone</span>
              <strong>{order.delivery_phone ?? order.customer_phone}</strong>
            </div>
            <div>
              <span>Address</span>
              <strong className="checkout-prewrap">{order.delivery_address}</strong>
            </div>
            {order.delivery_disclaimer ? <p className="summary-warning">{order.delivery_disclaimer}</p> : null}
          </div>
        </section>
      ) : null}

      {!isDeliveryOrder(order) && !isDineInOrder(order) ? (
        <section className="checkout-section" aria-labelledby="confirmation-pickup-heading">
          <div className="checkout-section-heading">
            <h2 id="confirmation-pickup-heading">Pickup</h2>
          </div>
          <div className="confirmation-detail-list">
            <div>
              <span>Name</span>
              <strong>{order.pickup_name ?? order.customer_name}</strong>
            </div>
            <div>
              <span>Phone</span>
              <strong>{order.pickup_phone ?? order.customer_phone}</strong>
            </div>
            {order.pickup_notes ? (
              <div>
                <span>Notes</span>
                <strong>{order.pickup_notes}</strong>
              </div>
            ) : null}
          </div>
        </section>
      ) : null}

      <section className="checkout-section" aria-labelledby="confirmation-items-heading">
        <div className="checkout-section-heading">
          <h2 id="confirmation-items-heading">Your items</h2>
        </div>

        <div className="checkout-list">
          {order.items.map((item) => (
            <CheckoutItemCard
              key={item.id}
              name={item.product_name}
              subtitle={joinLabels([item.variant_name])}
              quantity={item.quantity}
              unitPrice={item.unit_price}
              amount={item.line_subtotal}
              compact
            />
          ))}
        </div>

        <OrderTaxBreakdown
          subtotal={order.subtotal}
          total={order.total_amount}
          tax={order.tax}
          discounts={orderDiscountLines(order)}
          discountTotal={order.discount_total}
          totalLabel="Cafe total"
        />
      </section>

      <div className="confirmation-footer-actions">
        <DownloadInvoiceButton order={order} className="confirmation-invoice-action" />
        <Link to="/menu" className="link-button confirmation-continue">
          Continue shopping
        </Link>
      </div>
    </div>
  );
}
