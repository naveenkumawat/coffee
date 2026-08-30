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
  sync: (cart: Cart | null, summary: CartSummary | null) => void;
  hydrateGuest: () => void;
  refreshCount: () => Promise<void>;
  loadCart: () => Promise<void>;
  addItem: (payload: CartItemMutationPayload) => Promise<ApiEnvelope<Cart>>;
  updateItemQuantity: (cartItemId: number, quantity: number) => Promise<void>;
  removeItem: (cartItemId: number) => Promise<void>;
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
    count: response.meta?.summary?.item_count ?? response.data.items.reduce((carry, item) => carry + item.quantity, 0),
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

export const useCartStore = create<CartState>((set) => ({
  count: 0,
  cart: null,
  summary: null,
  isLoading: false,
  sync: (cart, summary) => {
    set({
      cart,
      summary,
      count: summary?.item_count ?? cart?.items.reduce((carry, item) => carry + item.quantity, 0) ?? 0,
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

    const response = await addCartItem(payload);
    applyCartState(set, response);

    return response;
  },
  updateItemQuantity: async (cartItemId, quantity) => {
    if (!isSessionAuthenticated()) {
      const nextItems = updateGuestCartItemQuantity(readGuestCartItems(), cartItemId, quantity);
      writeGuestCartItems(nextItems);
      applyGuestState(set);

      return;
    }

    const response = await updateCartItem(cartItemId, { quantity });
    applyCartState(set, response);
  },
  removeItem: async (cartItemId) => {
    if (!isSessionAuthenticated()) {
      const nextItems = removeGuestCartItem(readGuestCartItems(), cartItemId);
      writeGuestCartItems(nextItems);
      applyGuestState(set);

      return;
    }

    const response = await removeCartItem(cartItemId);
    applyCartState(set, response);
  },
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
    set({ count: 0, cart: null, summary: null, isLoading: false });
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
