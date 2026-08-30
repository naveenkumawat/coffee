import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { ApiError } from '../api/client';
import { CartItemCard } from '../components/cart/CartItemCard';
import { StickyActionBar } from '../components/common/StickyActionBar';
import { EmptyState } from '../components/common/EmptyState';
import { ErrorState } from '../components/common/ErrorState';
import { LoadingSkeleton } from '../components/common/LoadingSkeleton';
import { PageHeader } from '../components/common/PageHeader';
import { FormFeedback } from '../components/forms/FormFeedback';
import { useCartStore } from '../stores/cartStore';
import { formatCurrency } from '../utils/format';

export function CartPage() {
  const cart = useCartStore((state) => state.cart);
  const summary = useCartStore((state) => state.summary);
  const isLoading = useCartStore((state) => state.isLoading);
  const loadCart = useCartStore((state) => state.loadCart);
  const updateItemQuantity = useCartStore((state) => state.updateItemQuantity);
  const removeItem = useCartStore((state) => state.removeItem);
  const clear = useCartStore((state) => state.clear);
  const navigate = useNavigate();
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [pendingItemId, setPendingItemId] = useState<number | null>(null);
  const [isClearing, setIsClearing] = useState(false);

  async function loadCartState(): Promise<void> {
    setErrorMessage(null);

    try {
      await loadCart();
    } catch (error) {
      setErrorMessage(error instanceof ApiError ? error.message : 'Unable to load your cart.');
    }
  }

  useEffect(() => {
    void loadCartState();
  }, [loadCart]);

  async function handleQuantityChange(cartItemId: number, quantity: number): Promise<void> {
    setPendingItemId(cartItemId);
    setErrorMessage(null);

    try {
      await updateItemQuantity(cartItemId, quantity);
    } catch (error) {
      setErrorMessage(error instanceof ApiError ? error.message : 'Unable to update your cart item.');
      await loadCartState();
    } finally {
      setPendingItemId(null);
    }
  }

  async function handleRemove(cartItemId: number): Promise<void> {
    setPendingItemId(cartItemId);
    setErrorMessage(null);

    try {
      await removeItem(cartItemId);
    } catch (error) {
      setErrorMessage(error instanceof ApiError ? error.message : 'Unable to remove this item right now.');
      await loadCartState();
    } finally {
      setPendingItemId(null);
    }
  }

  async function handleClear(): Promise<void> {
    setIsClearing(true);
    setErrorMessage(null);

    try {
      await clear();
    } catch (error) {
      setErrorMessage(error instanceof ApiError ? error.message : 'Unable to clear your cart right now.');
      await loadCartState();
    } finally {
      setIsClearing(false);
    }
  }

  return (
    <div className="page-container">
      <PageHeader
        title="Cart"
        description={summary ? `${summary.item_count} item(s) synced from the API` : 'Live server-backed cart state'}
        rightSlot={
          cart?.items.length ? (
            <button type="button" className="btn btn-link text-decoration-none text-dark" onClick={() => void handleClear()} disabled={isClearing}>
              {isClearing ? 'Clearing...' : 'Clear'}
            </button>
          ) : null
        }
      />

      {isLoading ? <LoadingSkeleton cardCount={2} lines={4} /> : null}
      {!isLoading && errorMessage ? <FormFeedback message={errorMessage} variant="error" /> : null}
      {!isLoading && errorMessage && !cart?.items.length ? <ErrorState description={errorMessage} onRetry={() => void loadCartState()} /> : null}
      {!isLoading && !errorMessage && (!cart || cart.items.length === 0) ? (
        <EmptyState title="Your cart is empty" description="Add a few items from the menu to see them here." actionLabel="Browse menu" actionHref="/menu" />
      ) : null}
      {!isLoading && cart?.items.length ? (
        <>
          <div className="cart-list">
            {cart.items.map((item) => (
              <CartItemCard
                key={item.id}
                item={item}
                isBusy={pendingItemId === item.id}
                onChangeQuantity={(quantity) => void handleQuantityChange(item.id, quantity)}
                onRemove={() => void handleRemove(item.id)}
              />
            ))}
          </div>
          <section className="summary-card">
            <div>
              <span>Subtotal</span>
              <strong>{formatCurrency(summary?.subtotal ?? 0)}</strong>
            </div>
            <div>
              <span>Total</span>
              <strong>{formatCurrency(summary?.total ?? 0)}</strong>
            </div>
            {summary?.has_unavailable_items ? (
              <p className="summary-warning">One or more items need a live availability refresh before checkout.</p>
            ) : null}
          </section>
          <StickyActionBar
            eyebrow="Server totals"
            title="Ready to checkout?"
            value={formatCurrency(summary?.total ?? 0)}
            note={summary?.has_unavailable_items ? 'Please fix unavailable items before continuing.' : 'The backend remains authoritative for totals and availability.'}
          >
            <div className="sticky-action-stack">
              <button
                type="button"
                className="btn btn-primary btn-lg rounded-pill w-100"
                disabled={Boolean(summary?.has_unavailable_items) || isClearing || pendingItemId !== null}
                onClick={() => navigate('/checkout')}
              >
                Continue to checkout
              </button>
              <Link to="/menu" className="btn btn-outline-dark btn-lg rounded-pill w-100">
                Add more items
              </Link>
            </div>
          </StickyActionBar>
        </>
      ) : null}
    </div>
  );
}
