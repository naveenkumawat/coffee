import { create } from 'zustand';
import { addCartItem, clearCart, fetchCart, fetchCartCount, removeCartItem, updateCartItem } from '../api/cart';
import { ApiEnvelope, ApiError } from '../api/client';
import { Cart, CartEnvelopeMeta, CartItemMutationPayload, CartSummary } from '../types/cart';

interface CartState {
  count: number;
  cart: Cart | null;
  summary: CartSummary | null;
  isLoading: boolean;
  sync: (cart: Cart | null, summary: CartSummary | null) => void;
  refreshCount: () => Promise<void>;
  loadCart: () => Promise<void>;
  addItem: (payload: CartItemMutationPayload) => Promise<ApiEnvelope<Cart>>;
  updateItemQuantity: (cartItemId: number, quantity: number) => Promise<void>;
  removeItem: (cartItemId: number) => Promise<void>;
  reset: () => void;
  clear: () => Promise<void>;
}

function applyCartState(
  set: (value: Partial<CartState>) => void,
  response: ApiEnvelope<Cart> & CartEnvelopeMeta
): void {
  set({
    cart: response.data,
    count: response.meta?.summary?.item_count ?? response.data.items.reduce((carry, item) => carry + item.quantity, 0),
    summary: response.meta?.summary ?? null
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
      count: summary?.item_count ?? cart?.items.reduce((carry, item) => carry + item.quantity, 0) ?? 0
    });
  },
  refreshCount: async () => {
    try {
      const response = await fetchCartCount();
      set({ count: response.data.count });
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) {
        set({ count: 0, cart: null, summary: null });
      }
    }
  },
  loadCart: async () => {
    set({ isLoading: true });

    try {
      const response = await fetchCart();
      applyCartState(set, response);
    } finally {
      set({ isLoading: false });
    }
  },
  addItem: async (payload) => {
    const response = await addCartItem(payload);
    applyCartState(set, response);

    return response;
  },
  updateItemQuantity: async (cartItemId, quantity) => {
    const response = await updateCartItem(cartItemId, { quantity });
    applyCartState(set, response);
  },
  removeItem: async (cartItemId) => {
    const response = await removeCartItem(cartItemId);
    applyCartState(set, response);
  },
  reset: () => {
    set({ count: 0, cart: null, summary: null, isLoading: false });
  },
  clear: async () => {
    const response = await clearCart();
    applyCartState(set, response);
  }
}));
