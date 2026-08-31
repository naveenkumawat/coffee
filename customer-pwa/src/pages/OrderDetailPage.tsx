import { useEffect, useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { ApiError } from '../api/client';
import { fetchOrder } from '../api/orders';
import { RatingSheet } from '../components/catalog/RatingSheet';
import { CheckoutItemCard } from '../components/checkout/CheckoutItemCard';
import { PaymentInstructionsCard } from '../components/checkout/PaymentInstructionsCard';
import { EmptyState } from '../components/common/EmptyState';
import { ErrorState } from '../components/common/ErrorState';
import { LoadingSkeleton } from '../components/common/LoadingSkeleton';
import { PageHeader } from '../components/common/PageHeader';
import { OrderStatusBadge } from '../components/orders/OrderStatusBadge';
import { OrderStatusTimeline } from '../components/orders/OrderStatusTimeline';
import { Order, OrderItem, OrderPaymentInstructions } from '../types/order';
import { MyProductRating, RatingSummary } from '../types/rating';
import { formatCurrency, formatDateTime, joinLabels } from '../utils/format';
import { isPendingPayment, statusTone } from '../utils/orders';

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
      </section>

      {pendingPayment ? (
        <PaymentInstructionsCard
          order={order}
          payment={payment}
          showSecondaryAction={false}
          onOrderUpdated={setOrder}
        />
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
          {order.items.map((item: OrderItem) => {
            const showRateCta =
              order.status === 'completed' && item.can_rate && item.product_id !== null && ratedProductIds.has(item.id);

            return (
              <div key={item.id} className="order-item-with-rating">
                <CheckoutItemCard
                  name={item.product_name}
                  subtitle={joinLabels([item.variant_name, item.customer_ingredient_summary])}
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
