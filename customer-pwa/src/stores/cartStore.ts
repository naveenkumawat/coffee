import { create } from 'zustand';
import {
  addCartItem,
  clearCart,
  fetchCart,
  fetchCartCount,
  mergeGuestCart,
  removeCartItem,
  updateCartItem,
} from '../api/cart';
import { ApiEnvelope, ApiError } from '../api/client';
import { Cart, CartEnvelopeMeta, CartItemMutationPayload, CartSummary } from '../types/cart';
import { isSessionAuthenticated } from '../utils/authSession';
import {
  CART_MAX_QUANTITY,
  findCartItemByVariantId,
  totalCartQuantity,
} from '../utils/cartQuantity';
import {
  buildGuestCartState,
  clearGuestCartItems,
  clearMergeIdempotencyKey,
  getOrCreateMergeIdempotencyKey,
  guestItemsForMerge,
  readGuestCartItems,
  removeGuestCartItem,
  updateGuestCartItemQuantity,
  upsertGuestCartItem,
  writeGuestCartItems,
} from '../utils/guestCartStorage';

interface CartState {
  count: number;
  cart: Cart | null;
  summary: CartSummary | null;
  isLoading: boolean;
  pendingVariantIds: number[];
  sync: (cart: Cart | null, summary: CartSummary | null) => void;
  hydrateGuest: () => void;
  refreshCount: () => Promise<void>;
  loadCart: () => Promise<void>;
  addItem: (payload: CartItemMutationPayload) => Promise<ApiEnvelope<Cart>>;
  updateItemQuantity: (cartItemId: number, quantity: number) => Promise<void>;
  removeItem: (cartItemId: number) => Promise<void>;
  setVariantQuantity: (
    productVariantId: number,
    quantity: number,
    display?: CartItemMutationPayload['display'],
  ) => Promise<void>;
  isVariantPending: (productVariantId: number) => boolean;
  mergeGuestCart: () => Promise<boolean>;
  reset: () => void;
  clear: () => Promise<void>;
}

function applyCartState(
  set: (value: Partial<CartState>) => void,
  response: ApiEnvelope<Cart> & CartEnvelopeMeta,
): void {
  set({
    cart: response.data,
    count: totalCartQuantity(response.data, response.meta?.summary ?? null),
    summary: response.meta?.summary ?? null,
  });
}

function applyGuestState(set: (value: Partial<CartState>) => void): void {
  const { cart, summary } = buildGuestCartState(readGuestCartItems());
  set({
    cart,
    summary,
    count: summary.item_count,
  });
}

function withPendingVariant(
  set: (
    partial: Partial<CartState> | ((state: CartState) => Partial<CartState>),
  ) => void,
  get: () => CartState,
  productVariantId: number,
): () => void {
  set((state) => ({
    pendingVariantIds: state.pendingVariantIds.includes(productVariantId)
      ? state.pendingVariantIds
      : [...state.pendingVariantIds, productVariantId],
  }));

  return () => {
    set((state) => ({
      pendingVariantIds: state.pendingVariantIds.filter((id) => id !== productVariantId),
    }));
  };
}

function clampQuantity(quantity: number): number {
  return Math.max(0, Math.min(CART_MAX_QUANTITY, quantity));
}

function rebuildSummaryFromCart(cart: Cart): CartSummary {
  const itemCount = cart.items.reduce((carry, item) => carry + item.quantity, 0);
  const subtotal = cart.items.reduce((carry, item) => {
    if (!item.is_available) {
      return carry;
    }

    return carry + Number(item.unit_price ?? item.variant?.price ?? 0) * item.quantity;
  }, 0);
  const money = subtotal.toFixed(2);

  return {
    item_count: itemCount,
    subtotal: money,
    total: money,
    has_unavailable_items: cart.items.some((item) => !item.is_available),
  };
}

function applyOptimisticQuantity(
  cart: Cart,
  productVariantId: number,
  quantity: number,
  display?: CartItemMutationPayload['display'],
): Cart {
  const existing = findCartItemByVariantId(cart, productVariantId);

  if (quantity <= 0) {
    return {
      ...cart,
      items: cart.items.filter((item) => item.variant?.id !== productVariantId),
    };
  }

  if (existing) {
    const unitPrice = existing.unit_price ?? existing.variant?.price ?? null;

    return {
      ...cart,
      items: cart.items.map((item) =>
        item.variant?.id === productVariantId
          ? {
              ...item,
              quantity,
              unit_price: unitPrice,
              line_total: unitPrice ? (Number(unitPrice) * quantity).toFixed(2) : item.line_total,
            }
          : item,
      ),
    };
  }

  const unitPrice = display?.variant?.price ?? null;

  return {
    ...cart,
    items: [
      ...cart.items,
      {
        id: -Math.abs(productVariantId),
        quantity,
        unit_price: unitPrice,
        line_total: unitPrice ? (Number(unitPrice) * quantity).toFixed(2) : null,
        is_available: true,
        product: display?.product ?? null,
        variant: display?.variant ?? { id: productVariantId, name: 'Selected size', serving_size_value: '', serving_size_unit: null, price: '0.00' },
      },
    ],
  };
}

export const useCartStore = create<CartState>((set, get) => ({
  count: 0,
  cart: null,
  summary: null,
  isLoading: false,
  pendingVariantIds: [],
  sync: (cart, summary) => {
    set({
      cart,
      summary,
      count: totalCartQuantity(cart, summary),
    });
  },
  hydrateGuest: () => {
    applyGuestState(set);
  },
  refreshCount: async () => {
    if (!isSessionAuthenticated()) {
      applyGuestState(set);

      return;
    }

    try {
      const response = await fetchCartCount();
      set({ count: response.data.count });
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) {
        applyGuestState(set);
      }
    }
  },
  loadCart: async () => {
    set({ isLoading: true });

    try {
      if (!isSessionAuthenticated()) {
        applyGuestState(set);

        return;
      }

      const response = await fetchCart();
      applyCartState(set, response);
    } finally {
      set({ isLoading: false });
    }
  },
  addItem: async (payload) => {
    const release = withPendingVariant(set, get, payload.product_variant_id);

    try {
      if (!isSessionAuthenticated()) {
        const nextItems = upsertGuestCartItem(readGuestCartItems(), payload);
        writeGuestCartItems(nextItems);
        const state = buildGuestCartState(nextItems);
        set({
          cart: state.cart,
          summary: state.summary,
          count: state.summary.item_count,
        });

        return {
          message: 'Added to cart.',
          data: state.cart,
          meta: { summary: state.summary },
        } as ApiEnvelope<Cart> & CartEnvelopeMeta;
      }

      const snapshot = {
        cart: get().cart,
        summary: get().summary,
        count: get().count,
      };
      const baseCart = snapshot.cart ?? {
        id: 0,
        items: [],
        created_at: null,
        updated_at: null,
      };
      const existingQty = findCartItemByVariantId(baseCart, payload.product_variant_id)?.quantity ?? 0;
      const optimisticCart = applyOptimisticQuantity(
        baseCart,
        payload.product_variant_id,
        clampQuantity(existingQty + payload.quantity),
        payload.display,
      );
      const optimisticSummary = rebuildSummaryFromCart(optimisticCart);
      set({
        cart: optimisticCart,
        summary: optimisticSummary,
        count: optimisticSummary.item_count,
      });

      try {
        const response = await addCartItem(payload);
        applyCartState(set, response);

        return response;
      } catch (error) {
        set(snapshot);
        throw error;
      }
    } finally {
      release();
    }
  },
  updateItemQuantity: async (cartItemId, quantity) => {
    const item = get().cart?.items.find((entry) => entry.id === cartItemId) ?? null;
    const variantId = item?.variant?.id;
    const release = variantId ? withPendingVariant(set, get, variantId) : () => undefined;

    try {
      if (!isSessionAuthenticated()) {
        const nextItems = updateGuestCartItemQuantity(readGuestCartItems(), cartItemId, quantity);
        writeGuestCartItems(nextItems);
        applyGuestState(set);

        return;
      }

      const snapshot = {
        cart: get().cart,
        summary: get().summary,
        count: get().count,
      };

      if (snapshot.cart && variantId) {
        const optimisticCart = applyOptimisticQuantity(snapshot.cart, variantId, clampQuantity(quantity));
        const optimisticSummary = rebuildSummaryFromCart(optimisticCart);
        set({
          cart: optimisticCart,
          summary: optimisticSummary,
          count: optimisticSummary.item_count,
        });
      }

      try {
        const response = await updateCartItem(cartItemId, { quantity: clampQuantity(quantity) });
        applyCartState(set, response);
      } catch (error) {
        set(snapshot);
        throw error;
      }
    } finally {
      release();
    }
  },
  removeItem: async (cartItemId) => {
    const item = get().cart?.items.find((entry) => entry.id === cartItemId) ?? null;
    const variantId = item?.variant?.id;
    const release = variantId ? withPendingVariant(set, get, variantId) : () => undefined;

    try {
      if (!isSessionAuthenticated()) {
        const nextItems = removeGuestCartItem(readGuestCartItems(), cartItemId);
        writeGuestCartItems(nextItems);
        applyGuestState(set);

        return;
      }

      const snapshot = {
        cart: get().cart,
        summary: get().summary,
        count: get().count,
      };

      if (snapshot.cart && variantId) {
        const optimisticCart = applyOptimisticQuantity(snapshot.cart, variantId, 0);
        const optimisticSummary = rebuildSummaryFromCart(optimisticCart);
        set({
          cart: optimisticCart,
          summary: optimisticSummary,
          count: optimisticSummary.item_count,
        });
      }

      try {
        const response = await removeCartItem(cartItemId);
        applyCartState(set, response);
      } catch (error) {
        set(snapshot);
        throw error;
      }
    } finally {
      release();
    }
  },
  setVariantQuantity: async (productVariantId, quantity, display) => {
    const nextQuantity = clampQuantity(quantity);
    const state = get();

    if (state.pendingVariantIds.includes(productVariantId)) {
      return;
    }

    const existing = findCartItemByVariantId(state.cart, productVariantId);

    if (nextQuantity <= 0) {
      if (!existing) {
        return;
      }

      await get().removeItem(existing.id);

      return;
    }

    if (!existing) {
      await get().addItem({
        product_variant_id: productVariantId,
        quantity: nextQuantity,
        display,
      });

      return;
    }

    if (existing.quantity === nextQuantity) {
      return;
    }

    await get().updateItemQuantity(existing.id, nextQuantity);
  },
  isVariantPending: (productVariantId) => get().pendingVariantIds.includes(productVariantId),
  mergeGuestCart: async () => {
    const items = readGuestCartItems();

    if (items.length === 0) {
      clearMergeIdempotencyKey();

      return false;
    }

    const response = await mergeGuestCart({
      items: guestItemsForMerge(items),
      idempotency_key: getOrCreateMergeIdempotencyKey(),
    });

    clearGuestCartItems();
    applyCartState(set, response);

    return true;
  },
  reset: () => {
    set({ count: 0, cart: null, summary: null, isLoading: false, pendingVariantIds: [] });
  },
  clear: async () => {
    if (!isSessionAuthenticated()) {
      clearGuestCartItems();
      applyGuestState(set);

      return;
    }

    const response = await clearCart();
    applyCartState(set, response);
  },
}));
