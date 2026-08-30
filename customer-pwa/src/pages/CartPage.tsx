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
import { useAuthStore } from '../stores/authStore';
import { useCartStore } from '../stores/cartStore';
import { useToastStore } from '../stores/toastStore';
import { formatCurrency } from '../utils/format';
import { buildLoginRedirect } from '../utils/navigation';

export function CartPage() {
  const cart = useCartStore((state) => state.cart);
  const summary = useCartStore((state) => state.summary);
  const isLoading = useCartStore((state) => state.isLoading);
  const loadCart = useCartStore((state) => state.loadCart);
  const updateItemQuantity = useCartStore((state) => state.updateItemQuantity);
  const removeItem = useCartStore((state) => state.removeItem);
  const clear = useCartStore((state) => state.clear);
  const authStatus = useAuthStore((state) => state.status);
  const toastSuccess = useToastStore((state) => state.success);
  const toastError = useToastStore((state) => state.error);
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
  }, [loadCart, authStatus]);

  async function handleQuantityChange(cartItemId: number, quantity: number): Promise<void> {
    setPendingItemId(cartItemId);
    setErrorMessage(null);

    try {
      await updateItemQuantity(cartItemId, quantity);
      toastSuccess('Quantity updated');
    } catch (error) {
      const message = error instanceof ApiError ? error.message : 'Unable to update your cart item.';
      setErrorMessage(message);
      toastError(message);
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
      toastSuccess('Item removed');
    } catch (error) {
      const message = error instanceof ApiError ? error.message : 'Unable to remove this item right now.';
      setErrorMessage(message);
      toastError(message);
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
      toastSuccess('Cart cleared');
    } catch (error) {
      const message = error instanceof ApiError ? error.message : 'Unable to clear your cart right now.';
      setErrorMessage(message);
      toastError(message);
      await loadCartState();
    } finally {
      setIsClearing(false);
    }
  }

  function handleCheckout(): void {
    if (authStatus !== 'authenticated') {
      navigate(buildLoginRedirect('/checkout'));

      return;
    }

    navigate('/checkout');
  }

  return (
    <div className={`page-container ${cart?.items.length ? 'has-sticky-cta' : ''}`.trim()}>
      <PageHeader
        title="Cart"
        description={summary ? `${summary.item_count} item(s) ready for pickup` : 'Review your pickup order'}
        rightSlot={
          cart?.items.length ? (
            <button type="button" className="link-button" onClick={() => void handleClear()} disabled={isClearing}>
              {isClearing ? 'Clearing...' : 'Clear all'}
            </button>
          ) : null
        }
      />

      {isLoading ? <LoadingSkeleton cardCount={2} lines={3} variant="list" /> : null}
      {!isLoading && errorMessage ? <FormFeedback message={errorMessage} variant="error" /> : null}
      {!isLoading && errorMessage && !cart?.items.length ? (
        <ErrorState description={errorMessage} onRetry={() => void loadCartState()} />
      ) : null}
      {!isLoading && !errorMessage && (!cart || cart.items.length === 0) ? (
        <EmptyState
          title="Your cart is empty"
          description="Browse the menu and add a drink to get started."
          actionLabel="Browse menu"
          actionHref="/menu"
        />
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

          <section className="summary-card cart-summary-card">
            <div>
              <span>Subtotal</span>
              <strong>{formatCurrency(summary?.subtotal ?? 0)}</strong>
            </div>
            <div className="cart-summary-total">
              <span>Total</span>
              <strong>{formatCurrency(summary?.total ?? 0)}</strong>
            </div>
            {summary?.has_unavailable_items ? (
              <p className="summary-warning">Remove unavailable items before checkout.</p>
            ) : null}
            {authStatus !== 'authenticated' ? (
              <p className="summary-warning">Sign in is required at checkout. Your cart stays on this device until then.</p>
            ) : null}
            <Link to="/menu" className="link-button cart-add-more">
              Add more items
            </Link>
          </section>

          <StickyActionBar
            eyebrow="Pickup total"
            title="Ready to checkout?"
            value={formatCurrency(summary?.total ?? 0)}
            note={
              summary?.has_unavailable_items
                ? 'Fix unavailable items to continue.'
                : authStatus === 'authenticated'
                  ? 'Next: confirm pickup details'
                  : 'Next: sign in to continue'
            }
          >
            <button
              type="button"
              className="btn btn-primary btn-lg rounded-pill w-100"
              disabled={Boolean(summary?.has_unavailable_items) || isClearing || pendingItemId !== null}
              onClick={handleCheckout}
            >
              {authStatus === 'authenticated' ? 'Continue to checkout' : 'Sign in to checkout'}
            </button>
          </StickyActionBar>
        </>
      ) : null}
    </div>
  );
}
