import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { fetchProduct } from '../api/catalog';
import { ApiError } from '../api/client';
import { CartItemCard } from '../components/cart/CartItemCard';
import { CartOffersSection } from '../components/cart/CartOffersSection';
import { ProductCustomizationSheet } from '../components/catalog/ProductCustomizationSheet';
import { StickyActionBar } from '../components/common/StickyActionBar';
import { EmptyState } from '../components/common/EmptyState';
import { ErrorState } from '../components/common/ErrorState';
import { LoadingSkeleton } from '../components/common/LoadingSkeleton';
import { RecommendationSection } from '../components/recommendations/RecommendationSection';
import { FormFeedback } from '../components/forms/FormFeedback';
import { OrderTaxBreakdown } from '../components/orders/OrderTaxBreakdown';
import { useAuthStore } from '../stores/authStore';
import { useCartStore } from '../stores/cartStore';
import { selectAvailability, useContentStore } from '../stores/contentStore';
import { useToastStore } from '../stores/toastStore';
import { CartItem } from '../types/cart';
import { Product } from '../types/catalog';
import { selectionFromCartItem } from '../utils/cartQuantity';
import { cartDiscounts } from '../utils/discounts';
import { formatCurrency } from '../utils/format';
import { buildLoginRedirect } from '../utils/navigation';

interface EditingCartItem {
  item: CartItem;
  product: Product;
}

export function CartPage() {
  const cart = useCartStore((state) => state.cart);
  const summary = useCartStore((state) => state.summary);
  const isLoading = useCartStore((state) => state.isLoading);
  const loadCart = useCartStore((state) => state.loadCart);
  const updateItemQuantity = useCartStore((state) => state.updateItemQuantity);
  const removeItem = useCartStore((state) => state.removeItem);
  const clear = useCartStore((state) => state.clear);
  const applyPromoCode = useCartStore((state) => state.applyPromoCode);
  const clearPromoCode = useCartStore((state) => state.clearPromoCode);
  const applyFreeDrinkReward = useCartStore((state) => state.applyFreeDrinkReward);
  const applyReferralCoupon = useCartStore((state) => state.applyReferralCoupon);
  const clearReferralRewards = useCartStore((state) => state.clearReferralRewards);
  const applyLoyaltyReward = useCartStore((state) => state.applyLoyaltyReward);
  const clearLoyaltyReward = useCartStore((state) => state.clearLoyaltyReward);
  const authStatus = useAuthStore((state) => state.status);
  const availability = useContentStore((state) => selectAvailability(state.content));
  const orderingClosed = Boolean(availability && !availability.available);
  const toastSuccess = useToastStore((state) => state.success);
  const toastError = useToastStore((state) => state.error);
  const navigate = useNavigate();
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [pendingItemId, setPendingItemId] = useState<number | null>(null);
  const [isClearing, setIsClearing] = useState(false);
  const [editing, setEditing] = useState<EditingCartItem | null>(null);

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

  async function handleEdit(item: CartItem): Promise<void> {
    if (!item.product?.id) {
      toastError('Unable to edit this item right now.');

      return;
    }

    setPendingItemId(item.id);
    setErrorMessage(null);

    try {
      const response = await fetchProduct(String(item.product.id));
      setEditing({ item, product: response.data });
    } catch (error) {
      const message = error instanceof ApiError ? error.message : 'Unable to load product options.';
      setErrorMessage(message);
      toastError(message);
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
      <h1 className="visually-hidden">Cart</h1>
      {cart?.items.length ? (
        <div className="page-toolbar">
          <span className="page-toolbar-meta">
            {summary ? `${summary.item_count} item(s)` : 'Your cart'}
          </span>
          <button type="button" className="link-button" onClick={() => void handleClear()} disabled={isClearing}>
            {isClearing ? 'Clearing...' : 'Clear all'}
          </button>
        </div>
      ) : null}

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
                onEdit={() => void handleEdit(item)}
              />
            ))}
          </div>

          {authStatus === 'authenticated' ? (
            <CartOffersSection
              summary={summary}
              onApply={async (promoCode) => {
                await applyPromoCode(promoCode);
                toastSuccess('Promo code applied');
              }}
              onRemove={async () => {
                await clearPromoCode();
                toastSuccess('Promo code removed');
              }}
              onApplyFreeDrink={async (rewardId) => {
                await applyFreeDrinkReward(rewardId);
                toastSuccess('Free drink reward applied');
              }}
              onApplyReferralCoupon={async (code) => {
                await applyReferralCoupon(code);
                toastSuccess('Referral reward applied');
              }}
              onClearReferralRewards={async () => {
                await clearReferralRewards();
                toastSuccess('Referral reward removed');
              }}
              onApplyLoyaltyReward={async (rewardId) => {
                await applyLoyaltyReward(rewardId);
                toastSuccess('Loyalty reward applied');
              }}
              onClearLoyaltyReward={async () => {
                await clearLoyaltyReward();
                toastSuccess('Loyalty reward removed');
              }}
            />
          ) : (
            <p className="summary-warning cart-offers-signin">
              Sign in at checkout to apply promo codes. Automatic offers are calculated then too.
            </p>
          )}

          <RecommendationSection
            context="cart"
            placement="cart_rail"
            cartProductIds={cart.items
              .map((item) => item.product?.id)
              .filter((id): id is number => typeof id === 'number')}
            excludeProductIds={cart.items
              .map((item) => item.product?.id)
              .filter((id): id is number => typeof id === 'number')}
            limit={6}
            title="Complete your order"
          />

          <section className="cart-summary-card">
            <OrderTaxBreakdown
              subtotal={summary?.subtotal ?? 0}
              total={summary?.total ?? 0}
              tax={summary?.tax}
              discounts={cartDiscounts(summary)}
              discountTotal={summary?.discount_total}
              totalLabel="Total"
              showSavingsNote={false}
              estimateNote={
                summary?.tax
                  ? null
                  : 'Estimated item total — tax is confirmed at checkout.'
              }
            />
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
            eyebrow="Order total"
            title={orderingClosed ? 'Ordering Closed' : 'Ready to checkout?'}
            value={formatCurrency(summary?.total ?? 0)}
            note={
              orderingClosed
                ? availability?.message ?? 'Checkout unavailable right now.'
                : summary?.has_unavailable_items
                  ? 'Fix unavailable items to continue.'
                  : authStatus === 'authenticated'
                    ? 'Next: choose takeaway or delivery'
                    : 'Next: sign in to checkout'
            }
          >
            <button
              type="button"
              className="btn btn-primary btn-lg rounded-pill w-100"
              disabled={
                orderingClosed
                || Boolean(summary?.has_unavailable_items)
                || isClearing
                || pendingItemId !== null
              }
              onClick={handleCheckout}
            >
              {orderingClosed
                ? 'Checkout unavailable'
                : authStatus === 'authenticated'
                  ? 'Continue to checkout'
                  : 'Sign in to checkout'}
            </button>
          </StickyActionBar>
        </>
      ) : null}

      {editing ? (
        <ProductCustomizationSheet
          product={editing.product}
          open
          onClose={() => setEditing(null)}
          cartItemId={editing.item.id}
          initialVariantId={editing.item.variant?.id}
          initialAddOns={selectionFromCartItem(editing.item)}
          initialQuantity={editing.item.quantity}
          onSaved={() => {
            setEditing(null);
            void loadCartState();
          }}
        />
      ) : null}
    </div>
  );
}
