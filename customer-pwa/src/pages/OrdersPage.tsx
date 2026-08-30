import { FormEvent, useEffect, useMemo, useState } from 'react';
import { fetchOrders } from '../api/orders';
import { ApiError } from '../api/client';
import { EmptyState } from '../components/common/EmptyState';
import { ErrorState } from '../components/common/ErrorState';
import { LoadingSkeleton } from '../components/common/LoadingSkeleton';
import { PageHeader } from '../components/common/PageHeader';
import { OrderListCard } from '../components/orders/OrderListCard';
import { Order } from '../types/order';
import { isActiveOrder, sortOrdersForDisplay } from '../utils/orders';

export function OrdersPage() {
  const [orders, setOrders] = useState<Order[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  async function loadOrders(nextPage = 1, append = false): Promise<void> {
    if (append) {
      setIsLoadingMore(true);
    } else {
      setIsLoading(true);
      setErrorMessage(null);
    }

    try {
      const response = await fetchOrders(nextPage);
      setOrders((current) => (append ? [...current, ...response.data] : response.data));
      setPage(response.meta?.pagination?.current_page ?? nextPage);
      setLastPage(response.meta?.pagination?.last_page ?? 1);
    } catch (error) {
      setErrorMessage(error instanceof ApiError ? error.message : 'Unable to load your orders.');
      if (!append) {
        setOrders([]);
      }
    } finally {
      setIsLoading(false);
      setIsLoadingMore(false);
    }
  }

  useEffect(() => {
    void loadOrders(1);
  }, []);

  function handleLoadMore(event: FormEvent): void {
    event.preventDefault();
    void loadOrders(page + 1, true);
  }

  const displayOrders = useMemo(() => sortOrdersForDisplay(orders), [orders]);
  const activeCount = useMemo(
    () => displayOrders.filter((order) => isActiveOrder(order.status)).length,
    [displayOrders],
  );

  return (
    <div className="page-container orders-page">
      <PageHeader
        title="Orders"
        description={
          activeCount > 0
            ? `${activeCount} active · newest statuses first`
            : 'Your pickup orders and live status'
        }
        rightSlot={
          <button type="button" className="link-button" onClick={() => void loadOrders(1)} disabled={isLoading}>
            {isLoading ? 'Refreshing…' : 'Refresh'}
          </button>
        }
      />

      {isLoading ? <LoadingSkeleton cardCount={3} lines={3} variant="list" /> : null}
      {!isLoading && errorMessage ? <ErrorState description={errorMessage} onRetry={() => void loadOrders(1)} /> : null}
      {!isLoading && !errorMessage && orders.length === 0 ? (
        <EmptyState
          title="No orders yet"
          description="Place a pickup order and track payment and preparation here."
          actionLabel="Browse menu"
          actionHref="/menu"
        />
      ) : null}
      {!isLoading && !errorMessage && displayOrders.length > 0 ? (
        <>
          <div className="order-list">
            {displayOrders.map((order) => (
              <OrderListCard key={order.id} order={order} />
            ))}
          </div>
          {page < lastPage ? (
            <form className="order-load-more" onSubmit={handleLoadMore}>
              <button type="submit" className="btn btn-outline-dark btn-lg rounded-pill w-100" disabled={isLoadingMore}>
                {isLoadingMore ? 'Loading…' : 'Load older orders'}
              </button>
            </form>
          ) : null}
        </>
      ) : null}
    </div>
  );
}
