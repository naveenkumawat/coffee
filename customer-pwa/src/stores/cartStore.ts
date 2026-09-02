import { create } from 'zustand';
import {
  addCartItem,
  applyCartPromoCode,
  applyFreeDrinkReward,
  applyReferralCouponReward,
  clearCart,
  clearCartPromoCode,
  clearReferralRewards,
  fetchCart,
  fetchCartCount,
  mergeGuestCart,
  removeCartItem,
  updateCartItem,
} from '../api/cart';
import { ApiEnvelope, ApiError } from '../api/client';
import {
  Cart,
  CartAddOnSelection,
  CartEnvelopeMeta,
  CartItemMutationPayload,
  CartSummary,
} from '../types/cart';
import { CheckoutFulfilmentMethod } from '../types/checkout';
import { addonUnitTotal, addOnsConfigurationKey, canonicalizeAddOns } from '../utils/addOns';
import { isSessionAuthenticated } from '../utils/authSession';
import {
  CART_MAX_QUANTITY,
  findCartItemByConfiguration,
} from '../utils/cartQuantity';
import {
  buildGuestCartState,
  clearGuestCartItems,
  clearMergeIdempotencyKey,
  getOrCreateMergeIdempotencyKey,
  guestItemsForMerge,
  readGuestCartItems,
  removeGuestCartItem,
  replaceGuestCartItem,
  updateGuestCartItemQuantity,
  upsertGuestCartItem,
  writeGuestCartItems,
} from '../utils/guestCartStorage';
import { totalCartQuantity } from '../utils/cartQuantity';

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
  replaceConfiguredItem: (cartItemId: number, payload: CartItemMutationPayload) => Promise<void>;
  removeItem: (cartItemId: number) => Promise<void>;
  setVariantQuantity: (
    productVariantId: number,
    quantity: number,
    display?: CartItemMutationPayload['display'],
    addOns?: CartAddOnSelection[],
  ) => Promise<void>;
  isVariantPending: (productVariantId: number) => boolean;
  mergeGuestCart: () => Promise<boolean>;
  applyPromoCode: (
    promoCode: string,
    fulfilmentMethod?: CheckoutFulfilmentMethod | null,
  ) => Promise<void>;
  clearPromoCode: () => Promise<void>;
  applyFreeDrinkReward: (
    rewardId: number,
    fulfilmentMethod?: CheckoutFulfilmentMethod | null,
  ) => Promise<void>;
  applyReferralCoupon: (
    code: string,
    fulfilmentMethod?: CheckoutFulfilmentMethod | null,
  ) => Promise<void>;
  clearReferralRewards: (fulfilmentMethod?: CheckoutFulfilmentMethod | null) => Promise<void>;
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

    return carry + Number(item.line_total ?? Number(item.unit_price ?? item.variant?.price ?? 0) * item.quantity);
  }, 0);
  const money = subtotal.toFixed(2);

  return {
    item_count: itemCount,
    subtotal: money,
    discount_total: '0.00',
    discounts: [],
    promo_code: null,
    promo_error: null,
    total: money,
    has_unavailable_items: cart.items.some((item) => !item.is_available),
  };
}

function applyOptimisticQuantity(
  cart: Cart,
  productVariantId: number,
  quantity: number,
  display?: CartItemMutationPayload['display'],
  addOns: CartAddOnSelection[] = [],
): Cart {
  const key = addOnsConfigurationKey(productVariantId, addOns);
  const existing = findCartItemByConfiguration(cart, productVariantId, addOns);

  if (quantity <= 0) {
    return {
      ...cart,
      items: cart.items.filter(
        (item) =>
          !(
            item.variant?.id === productVariantId &&
            addOnsConfigurationKey(productVariantId, item.add_ons) === key
          ),
      ),
    };
  }

  const displayAddOns = display?.add_ons ?? existing?.add_ons ?? [];
  const baseUnit = Number(display?.variant?.price ?? existing?.base_unit_price ?? existing?.variant?.price ?? 0);
  const addonUnit = addonUnitTotal(displayAddOns);
  const unitPrice = (baseUnit + addonUnit).toFixed(2);

  if (existing) {
    return {
      ...cart,
      items: cart.items.map((item) =>
        item.id === existing.id
          ? {
              ...item,
              quantity,
              unit_price: unitPrice,
              line_total: (Number(unitPrice) * quantity).toFixed(2),
              base_unit_price: baseUnit.toFixed(2),
              base_line_total: (baseUnit * quantity).toFixed(2),
              addon_line_total: (addonUnit * quantity).toFixed(2),
              add_ons: displayAddOns,
            }
          : item,
      ),
    };
  }

  return {
    ...cart,
    items: [
      ...cart.items,
      {
        id: -Math.abs(productVariantId),
        quantity,
        unit_price: unitPrice,
        line_total: (Number(unitPrice) * quantity).toFixed(2),
        base_unit_price: baseUnit.toFixed(2),
        base_line_total: (baseUnit * quantity).toFixed(2),
        addon_line_total: (addonUnit * quantity).toFixed(2),
        add_ons: displayAddOns,
        is_available: true,
        product: display?.product ?? null,
        variant: display?.variant ?? {
          id: productVariantId,
          name: 'Selected size',
          serving_size_value: '',
          serving_size_unit: null,
          price: '0.00',
        },
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
    const release = withPendingVariant(set, payload.product_variant_id);
    const addOns = canonicalizeAddOns(payload.add_ons);

    try {
      if (!isSessionAuthenticated()) {
        const nextItems = upsertGuestCartItem(readGuestCartItems(), {
          ...payload,
          add_ons: addOns,
        });
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
      const existingQty =
        findCartItemByConfiguration(baseCart, payload.product_variant_id, addOns)?.quantity ?? 0;
      const optimisticCart = applyOptimisticQuantity(
        baseCart,
        payload.product_variant_id,
        clampQuantity(existingQty + payload.quantity),
        payload.display,
        addOns,
      );
      const optimisticSummary = rebuildSummaryFromCart(optimisticCart);
      set({
        cart: optimisticCart,
        summary: optimisticSummary,
        count: optimisticSummary.item_count,
      });

      try {
        const response = await addCartItem({ ...payload, add_ons: addOns });
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
    const addOns = canonicalizeAddOns(item?.add_ons);
    const release = variantId ? withPendingVariant(set, variantId) : () => undefined;

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
        const optimisticCart = applyOptimisticQuantity(
          snapshot.cart,
          variantId,
          clampQuantity(quantity),
          undefined,
          addOns,
        );
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
  replaceConfiguredItem: async (cartItemId, payload) => {
    const addOns = canonicalizeAddOns(payload.add_ons);
    const release = withPendingVariant(set, payload.product_variant_id);

    try {
      if (!isSessionAuthenticated()) {
        const nextItems = replaceGuestCartItem(readGuestCartItems(), cartItemId, {
          ...payload,
          add_ons: addOns,
        });
        writeGuestCartItems(nextItems);
        applyGuestState(set);

        return;
      }

      const snapshot = {
        cart: get().cart,
        summary: get().summary,
        count: get().count,
      };

      try {
        await removeCartItem(cartItemId);
        const response = await addCartItem({
          ...payload,
          add_ons: addOns,
          quantity: clampQuantity(payload.quantity),
        });
        applyCartState(set, response);
      } catch (error) {
        set(snapshot);
        await get().loadCart();
        throw error;
      }
    } finally {
      release();
    }
  },
  removeItem: async (cartItemId) => {
    const item = get().cart?.items.find((entry) => entry.id === cartItemId) ?? null;
    const variantId = item?.variant?.id;
    const addOns = canonicalizeAddOns(item?.add_ons);
    const release = variantId ? withPendingVariant(set, variantId) : () => undefined;

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
        const optimisticCart = applyOptimisticQuantity(snapshot.cart, variantId, 0, undefined, addOns);
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
  setVariantQuantity: async (productVariantId, quantity, display, addOns = []) => {
    const nextQuantity = clampQuantity(quantity);
    const selectedAddOns = canonicalizeAddOns(addOns);
    const state = get();

    if (state.pendingVariantIds.includes(productVariantId)) {
      return;
    }

    const existing = findCartItemByConfiguration(state.cart, productVariantId, selectedAddOns);

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
        add_ons: selectedAddOns,
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
  applyPromoCode: async (promoCode, fulfilmentMethod = null) => {
    if (!isSessionAuthenticated()) {
      throw new ApiError('Sign in to apply a promo code.', 401);
    }

    const response = await applyCartPromoCode({
      promo_code: promoCode,
      ...(fulfilmentMethod ? { fulfilment_method: fulfilmentMethod } : {}),
    });
    applyCartState(set, response);
  },
  clearPromoCode: async () => {
    if (!isSessionAuthenticated()) {
      throw new ApiError('Sign in to manage promo codes.', 401);
    }

    const response = await clearCartPromoCode();
    applyCartState(set, response);
  },
  applyFreeDrinkReward: async (rewardId, fulfilmentMethod = null) => {
    if (!isSessionAuthenticated()) {
      throw new ApiError('Sign in to apply rewards.', 401);
    }

    const response = await applyFreeDrinkReward({
      reward_id: rewardId,
      ...(fulfilmentMethod ? { fulfilment_method: fulfilmentMethod } : {}),
    });
    applyCartState(set, response);
  },
  applyReferralCoupon: async (code, fulfilmentMethod = null) => {
    if (!isSessionAuthenticated()) {
      throw new ApiError('Sign in to apply rewards.', 401);
    }

    const response = await applyReferralCouponReward({
      referral_coupon: code,
      ...(fulfilmentMethod ? { fulfilment_method: fulfilmentMethod } : {}),
    });
    applyCartState(set, response);
  },
  clearReferralRewards: async (fulfilmentMethod = null) => {
    if (!isSessionAuthenticated()) {
      throw new ApiError('Sign in to manage rewards.', 401);
    }

    const response = await clearReferralRewards(fulfilmentMethod);
    applyCartState(set, response);
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
