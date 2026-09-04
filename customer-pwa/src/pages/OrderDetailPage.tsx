import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { ApiError } from '../api/client';
import { cancelOrder, fetchOrder } from '../api/orders';
import { RatingSheet } from '../components/catalog/RatingSheet';
import { CheckoutItemCard } from '../components/checkout/CheckoutItemCard';
import { PaymentInstructionsCard } from '../components/checkout/PaymentInstructionsCard';
import { EmptyState } from '../components/common/EmptyState';
import { ErrorState } from '../components/common/ErrorState';
import { confirmYes } from '../components/common/ConfirmDialog';
import { LoadingSkeleton } from '../components/common/LoadingSkeleton';
import { PageHeader } from '../components/common/PageHeader';
import { DownloadInvoiceButton } from '../components/orders/DownloadInvoiceButton';
import { OrderStatusBadge } from '../components/orders/OrderStatusBadge';
import { OrderStatusTimeline } from '../components/orders/OrderStatusTimeline';
import { OrderTaxBreakdown } from '../components/orders/OrderTaxBreakdown';
import { LoyaltyFeedbackBanner } from '../components/loyalty/LoyaltyFeedbackBanner';
import { useLiveCanonicalSync } from '../notifications/useLiveCanonicalSync';
import { Order, OrderItem, OrderPaymentInstructions } from '../types/order';
import { MyProductRating, RatingSummary } from '../types/rating';
import { discountAmount, orderDiscountLines } from '../utils/discounts';
import { formatCurrency, formatDateTime, joinLabels } from '../utils/format';
import { isCashPayment, isPendingPayment, statusTone } from '../utils/orders';

interface RatingTarget {
  productId: number;
  productName: string;
  myRating: MyProductRating | null;
}

export function OrderDetailPage() {
  const { orderId = '' } = useParams();
  const [order, setOrder] = useState<Order | null>(null);
  const [payment, setPayment] = useState<OrderPaymentInstructions | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [ratingTarget, setRatingTarget] = useState<RatingTarget | null>(null);
  const [isCancelling, setIsCancelling] = useState(false);
  const [cancelMessage, setCancelMessage] = useState<string | null>(null);

  const loadOrder = useCallback(async (soft = false): Promise<void> => {
    if (!orderId) {
      setErrorMessage('Order not found.');
      setIsLoading(false);
      return;
    }

    if (!soft) {
      setIsLoading(true);
      setErrorMessage(null);
    }

    try {
      const response = await fetchOrder(orderId);
      setOrder(response.data);
      setPayment(response.meta?.payment ?? null);
      setErrorMessage(null);
    } catch (error) {
      if (!soft) {
        setOrder(null);
        setPayment(null);
        setErrorMessage(error instanceof ApiError ? error.message : 'Unable to load this order.');
      }
    } finally {
      setIsLoading(false);
    }
  }, [orderId]);

  useEffect(() => {
    void loadOrder();
  }, [loadOrder]);

  useLiveCanonicalSync(
    () => loadOrder(true),
    (signal) => {
      if (signal.subject?.type === 'Order' && String(signal.subject.id) === String(orderId)) {
        return true;
      }

      return Boolean(signal.action_url && signal.action_url.includes(`/orders/${orderId}`));
    },
  );

  async function handleCancelOrder(): Promise<void> {
    if (!order || isCancelling) {
      return;
    }

    const confirmed = await confirmYes({
      title: 'Cancel this unpaid order?',
      body: 'You can place a new order anytime.',
      confirmLabel: 'Cancel order',
      tone: 'danger',
    });
    if (!confirmed) {
      return;
    }

    setIsCancelling(true);
    setCancelMessage(null);

    try {
      const response = await cancelOrder(order.id);
      setOrder(response.data);
      setPayment(response.meta?.payment ?? null);
      setCancelMessage('Order cancelled.');
    } catch (error) {
      setCancelMessage(error instanceof ApiError ? error.message : 'Unable to cancel this order.');
    } finally {
      setIsCancelling(false);
    }
  }

  const ratedProductIds = useMemo(() => {
    if (!order || order.status !== 'completed') {
      return new Set<number>();
    }

    const firstIds = new Set<number>();
    const seen = new Set<number>();

    for (const item of order.items) {
      if (!item.product_id || seen.has(item.product_id)) {
        continue;
      }

      seen.add(item.product_id);
      firstIds.add(item.id);
    }

    return firstIds;
  }, [order]);

  function applyRatingToOrder(
    productId: number,
    payload: { my_rating: MyProductRating | null; rating_summary: RatingSummary; can_rate: boolean },
  ): void {
    setOrder((current) => {
      if (!current) {
        return current;
      }

      return {
        ...current,
        items: current.items.map((item) =>
          item.product_id === productId
            ? {
                ...item,
                my_rating: payload.my_rating,
                can_rate: payload.can_rate,
              }
            : item,
        ),
      };
    });
  }

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
        <PageHeader title="Order" description="Track your order status." showBack />
        <ErrorState description={errorMessage} onRetry={() => void loadOrder()} />
      </div>
    );
  }

  if (!order) {
    return (
      <div className="page-container">
        <PageHeader title="Order" description="Track your order status." showBack />
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
  const showPaymentCard = isCashPayment(order) || pendingPayment;
  const paymentDetailLabel = (() => {
    if (!isCashPayment(order)) {
      return order.payment_method_label ?? 'UPI / QR';
    }

    if (order.payment_status === 'confirmed' || order.cash_received_at) {
      return 'Cash — Paid';
    }

    if (order.fulfilment_method === 'dine_in') {
      return 'Cash — Pay at Cafe';
    }

    return 'Cash — Pay at Pickup';
  })();

  const freeDrinkBenefit = (order.reward_redemptions ?? [])
    .filter((redemption) => redemption.reward_type === 'free_drink')
    .reduce((sum, redemption) => sum + discountAmount(redemption.benefit_amount), 0);

  return (
    <div className="page-container order-detail-page">
      <PageHeader
        title="Order"
        description={
          order.fulfilment_method === 'delivery'
            ? 'Status, payment, and delivery details'
            : 'Status, payment, and pickup details'
        }
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
        <p className="order-status-meta">Payment · {paymentDetailLabel}</p>
        <DownloadInvoiceButton order={order} />
      </section>

      {showPaymentCard ? (
        <PaymentInstructionsCard
          order={order}
          payment={payment}
          showSecondaryAction={false}
          onOrderUpdated={setOrder}
          onCancelOrder={() => void handleCancelOrder()}
          isCancelling={isCancelling}
          cancelError={cancelMessage}
        />
      ) : null}

      {!showPaymentCard && order.can_cancel && isPendingPayment(order.status) ? (
        <section className="account-section">
          {cancelMessage ? <p className="form-feedback is-error">{cancelMessage}</p> : null}
          <button
            type="button"
            className="btn btn-outline-danger btn-sm rounded-pill w-100"
            disabled={isCancelling}
            onClick={() => void handleCancelOrder()}
          >
            {isCancelling ? 'Cancelling…' : 'Cancel Order'}
          </button>
        </section>
      ) : null}

      <OrderStatusTimeline order={order} />

      <LoyaltyFeedbackBanner feedback={order.loyalty_feedback} />

      <section className="account-section">
        <div className="account-section-heading">
          <div>
            <span className="auth-badge">Items</span>
            <h2>What you ordered</h2>
          </div>
        </div>

        <div className="checkout-list">
          {order.items.map((item: OrderItem) => {
            const showRateCta =
              order.status === 'completed' && item.can_rate && item.product_id !== null && ratedProductIds.has(item.id);

            return (
              <div key={item.id} className="order-item-with-rating">
                <CheckoutItemCard
                  name={item.product_name}
                  subtitle={joinLabels([item.variant_name, item.customer_ingredient_summary])}
                  addOns={item.add_ons}
                  quantity={item.quantity}
                  amount={item.line_subtotal}
                />
                {showRateCta && item.product_id ? (
                  <button
                    type="button"
                    className="link-button order-rate-cta"
                    onClick={() =>
                      setRatingTarget({
                        productId: item.product_id as number,
                        productName: item.product_name,
                        myRating: item.my_rating ?? null,
                      })
                    }
                  >
                    {item.my_rating ? `★ ${item.my_rating.rating}/5 · Edit rating` : '☆ Rate this item'}
                  </button>
                ) : null}
              </div>
            );
          })}
        </div>

        <OrderTaxBreakdown
          subtotal={order.subtotal}
          total={order.total_amount}
          tax={order.tax}
          discounts={orderDiscountLines(order)}
          discountTotal={order.discount_total}
          loyaltyDiscount={order.loyalty_discount_amount}
          loyaltyLabel={
            order.loyalty_reward?.benefit_label
            ?? order.loyalty_feedback?.benefit_label
            ?? order.loyalty_reward?.name
            ?? 'Loyalty reward'
          }
          freeDrinkBenefit={freeDrinkBenefit > 0 ? freeDrinkBenefit : null}
          deliveryFee={order.delivery_fee_amount}
          totalLabel="Total"
        />
      </section>

      <section className="account-section order-pickup-section">
        <div className="account-section-heading">
          <div>
            <span className="auth-badge">
              {order.fulfilment_method === 'delivery'
                ? 'Delivery'
                : order.fulfilment_method === 'dine_in'
                  ? 'Dine-in'
                  : 'Takeaway'}
            </span>
            <h2>
              {order.fulfilment_method === 'delivery'
                ? 'Delivery details'
                : order.fulfilment_method === 'dine_in'
                  ? 'Table details'
                  : 'Pickup details'}
            </h2>
          </div>
        </div>
        <div className="order-meta-grid">
          {order.fulfilment_method === 'delivery' ? (
            <>
              <div>
                <span>Contact</span>
                <strong>{order.delivery_contact_name || order.customer_name}</strong>
              </div>
              <div>
                <span>Phone</span>
                <strong>{order.delivery_phone}</strong>
              </div>
              <div>
                <span>Address</span>
                <strong style={{ whiteSpace: 'pre-wrap' }}>{order.delivery_address}</strong>
              </div>
              {order.delivery_notes ? (
                <div>
                  <span>Delivery notes</span>
                  <strong>{order.delivery_notes}</strong>
                </div>
              ) : null}
              {order.delivery_disclaimer ? (
                <div>
                  <span>Note</span>
                  <strong>{order.delivery_disclaimer}</strong>
                </div>
              ) : null}
            </>
          ) : order.fulfilment_method === 'dine_in' ? (
            <div>
              <span>Table</span>
              <strong>{order.table_name ?? '—'}</strong>
            </div>
          ) : (
            <>
              <div>
                <span>Name</span>
                <strong>{order.pickup_name}</strong>
              </div>
              <div>
                <span>Phone</span>
                <strong>{order.pickup_phone}</strong>
              </div>
              {order.pickup_notes ? (
                <div>
                  <span>Pickup notes</span>
                  <strong>{order.pickup_notes}</strong>
                </div>
              ) : null}
            </>
          )}
          {order.customer_notes ? (
            <div>
              <span>Notes for cafe</span>
              <strong>{order.customer_notes}</strong>
            </div>
          ) : null}
        </div>
      </section>

      <div className="page-note">
        <span>Need another order?</span>
        <Link to="/orders">Back to My Orders</Link>
      </div>

      {ratingTarget ? (
        <RatingSheet
          open
          productId={ratingTarget.productId}
          productName={ratingTarget.productName}
          initialRating={ratingTarget.myRating}
          onClose={() => setRatingTarget(null)}
          onSaved={(payload) => applyRatingToOrder(ratingTarget.productId, payload)}
        />
      ) : null}
    </div>
  );
}
