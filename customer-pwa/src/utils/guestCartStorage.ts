import {
  Cart,
  CartItem,
  CartItemMutationPayload,
  CartProductSummary,
  CartSummary,
  CartVariantSummary,
} from '../types/cart';

const STORAGE_KEY = 'coffee.guest-cart.v1';
const MERGE_KEY_STORAGE = 'coffee.guest-cart.merge-key';

export interface GuestCartStoredItem {
  product_variant_id: number;
  quantity: number;
  product: CartProductSummary | null;
  variant: CartVariantSummary | null;
}

function emptySummary(): CartSummary {
  return {
    item_count: 0,
    subtotal: '0.00',
    discount_total: '0.00',
    discounts: [],
    promo_code: null,
    promo_error: null,
    total: '0.00',
    has_unavailable_items: false,
  };
}

function money(value: string | number | null | undefined): string {
  const amount = Number(value ?? 0);

  if (Number.isNaN(amount)) {
    return '0.00';
  }

  return amount.toFixed(2);
}

function lineTotal(unitPrice: string | null | undefined, quantity: number): string {
  return money(Number(unitPrice ?? 0) * quantity);
}

export function readGuestCartItems(): GuestCartStoredItem[] {
  try {
    const raw = window.localStorage.getItem(STORAGE_KEY);

    if (!raw) {
      return [];
    }

    const parsed = JSON.parse(raw) as { items?: GuestCartStoredItem[] };

    return Array.isArray(parsed.items) ? parsed.items : [];
  } catch {
    return [];
  }
}

export function writeGuestCartItems(items: GuestCartStoredItem[]): void {
  window.localStorage.setItem(STORAGE_KEY, JSON.stringify({ items }));
}

export function clearGuestCartItems(): void {
  window.localStorage.removeItem(STORAGE_KEY);
  window.sessionStorage.removeItem(MERGE_KEY_STORAGE);
}

export function getOrCreateMergeIdempotencyKey(): string {
  const existing = window.sessionStorage.getItem(MERGE_KEY_STORAGE);

  if (existing) {
    return existing;
  }

  const key = typeof crypto !== 'undefined' && 'randomUUID' in crypto
    ? crypto.randomUUID()
    : `merge-${Date.now()}-${Math.random().toString(36).slice(2)}`;

  window.sessionStorage.setItem(MERGE_KEY_STORAGE, key);

  return key;
}

export function clearMergeIdempotencyKey(): void {
  window.sessionStorage.removeItem(MERGE_KEY_STORAGE);
}

export function guestItemId(productVariantId: number): number {
  return -Math.abs(productVariantId);
}

export function buildGuestCartState(items: GuestCartStoredItem[]): {
  cart: Cart;
  summary: CartSummary;
} {
  const cartItems: CartItem[] = items.map((item) => {
    const unitPrice = item.variant?.price ?? null;

    return {
      id: guestItemId(item.product_variant_id),
      quantity: item.quantity,
      unit_price: unitPrice,
      line_total: lineTotal(unitPrice, item.quantity),
      is_available: true,
      product: item.product,
      variant: item.variant,
    };
  });

  const itemCount = items.reduce((carry, item) => carry + item.quantity, 0);
  const subtotal = items.reduce((carry, item) => carry + Number(item.variant?.price ?? 0) * item.quantity, 0);
  const summary: CartSummary = {
    item_count: itemCount,
    subtotal: money(subtotal),
    discount_total: '0.00',
    discounts: [],
    promo_code: null,
    promo_error: null,
    total: money(subtotal),
    has_unavailable_items: false,
  };

  return {
    cart: {
      id: 0,
      items: cartItems,
      created_at: null,
      updated_at: null,
    },
    summary: itemCount > 0 ? summary : emptySummary(),
  };
}

export function upsertGuestCartItem(
  items: GuestCartStoredItem[],
  payload: CartItemMutationPayload,
): GuestCartStoredItem[] {
  const next = [...items];
  const existingIndex = next.findIndex((item) => item.product_variant_id === payload.product_variant_id);

  if (existingIndex >= 0) {
    next[existingIndex] = {
      ...next[existingIndex],
      quantity: Math.min(99, next[existingIndex].quantity + payload.quantity),
      product: payload.display?.product ?? next[existingIndex].product,
      variant: payload.display?.variant ?? next[existingIndex].variant,
    };

    return next;
  }

  next.push({
    product_variant_id: payload.product_variant_id,
    quantity: Math.min(99, payload.quantity),
    product: payload.display?.product ?? null,
    variant: payload.display?.variant ?? null,
  });

  return next;
}

export function updateGuestCartItemQuantity(
  items: GuestCartStoredItem[],
  cartItemId: number,
  quantity: number,
): GuestCartStoredItem[] {
  const variantId = Math.abs(cartItemId);

  return items
    .map((item) =>
      item.product_variant_id === variantId
        ? { ...item, quantity: Math.min(99, Math.max(1, quantity)) }
        : item,
    );
}

export function removeGuestCartItem(items: GuestCartStoredItem[], cartItemId: number): GuestCartStoredItem[] {
  const variantId = Math.abs(cartItemId);

  return items.filter((item) => item.product_variant_id !== variantId);
}

export function guestItemsForMerge(items: GuestCartStoredItem[]): Array<{
  product_variant_id: number;
  quantity: number;
}> {
  return items.map((item) => ({
    product_variant_id: item.product_variant_id,
    quantity: item.quantity,
  }));
}
