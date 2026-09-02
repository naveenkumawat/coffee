import {
  Cart,
  CartAddOnSelection,
  CartItem,
  CartItemAddOn,
  CartItemMutationPayload,
  CartProductSummary,
  CartSummary,
  CartVariantSummary,
} from '../types/cart';
import { addonUnitTotal, addOnsConfigurationKey, canonicalizeAddOns } from './addOns';

const STORAGE_KEY = 'coffee.guest-cart.v1';
const MERGE_KEY_STORAGE = 'coffee.guest-cart.merge-key';

export interface GuestCartStoredItem {
  id: number;
  product_variant_id: number;
  quantity: number;
  add_ons: CartAddOnSelection[];
  add_ons_display: CartItemAddOn[];
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

function nextGuestItemId(items: GuestCartStoredItem[]): number {
  const minId = items.reduce((min, item) => Math.min(min, item.id), 0);

  return minId - 1;
}

function normalizeStoredItem(raw: Partial<GuestCartStoredItem> & { product_variant_id: number }): GuestCartStoredItem {
  const addOns = canonicalizeAddOns(raw.add_ons);
  const addOnsDisplay = Array.isArray(raw.add_ons_display) ? raw.add_ons_display : [];

  return {
    id: typeof raw.id === 'number' && raw.id < 0 ? raw.id : -Math.abs(raw.product_variant_id),
    product_variant_id: raw.product_variant_id,
    quantity: Math.min(99, Math.max(1, Number(raw.quantity) || 1)),
    add_ons: addOns,
    add_ons_display: addOnsDisplay,
    product: raw.product ?? null,
    variant: raw.variant ?? null,
  };
}

function unitTotals(item: GuestCartStoredItem): {
  baseUnit: number;
  addonUnit: number;
  unit: number;
} {
  const baseUnit = Number(item.variant?.price ?? 0);
  const addonUnit = addonUnitTotal(item.add_ons_display);
  const unit = baseUnit + addonUnit;

  return { baseUnit, addonUnit, unit };
}

export function readGuestCartItems(): GuestCartStoredItem[] {
  try {
    const raw = window.localStorage.getItem(STORAGE_KEY);

    if (!raw) {
      return [];
    }

    const parsed = JSON.parse(raw) as { items?: Array<Partial<GuestCartStoredItem> & { product_variant_id: number }> };

    if (!Array.isArray(parsed.items)) {
      return [];
    }

    return parsed.items
      .filter((item) => Number(item.product_variant_id) > 0)
      .map((item) => normalizeStoredItem(item));
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

export function buildGuestCartState(items: GuestCartStoredItem[]): {
  cart: Cart;
  summary: CartSummary;
} {
  const cartItems: CartItem[] = items.map((item) => {
    const { baseUnit, addonUnit, unit } = unitTotals(item);
    const quantity = item.quantity;
    const addOnsDisplay = item.add_ons_display;

    return {
      id: item.id,
      quantity,
      unit_price: money(unit),
      line_total: money(unit * quantity),
      base_unit_price: money(baseUnit),
      base_line_total: money(baseUnit * quantity),
      addon_line_total: money(addonUnit * quantity),
      add_ons: addOnsDisplay,
      is_available: true,
      product: item.product,
      variant: item.variant,
    };
  });

  const itemCount = items.reduce((carry, item) => carry + item.quantity, 0);
  const subtotal = cartItems.reduce((carry, item) => carry + Number(item.line_total ?? 0), 0);
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
  const addOns = canonicalizeAddOns(payload.add_ons);
  const key = addOnsConfigurationKey(payload.product_variant_id, addOns);
  const next = [...items];
  const existingIndex = next.findIndex(
    (item) => addOnsConfigurationKey(item.product_variant_id, item.add_ons) === key,
  );
  const displayAddOns = payload.display?.add_ons ?? next[existingIndex]?.add_ons_display ?? [];

  if (existingIndex >= 0) {
    next[existingIndex] = {
      ...next[existingIndex],
      quantity: Math.min(99, next[existingIndex].quantity + payload.quantity),
      add_ons: addOns,
      add_ons_display: displayAddOns,
      product: payload.display?.product ?? next[existingIndex].product,
      variant: payload.display?.variant ?? next[existingIndex].variant,
    };

    return next;
  }

  next.push({
    id: nextGuestItemId(next),
    product_variant_id: payload.product_variant_id,
    quantity: Math.min(99, payload.quantity),
    add_ons: addOns,
    add_ons_display: displayAddOns,
    product: payload.display?.product ?? null,
    variant: payload.display?.variant ?? null,
  });

  return next;
}

export function replaceGuestCartItem(
  items: GuestCartStoredItem[],
  cartItemId: number,
  payload: CartItemMutationPayload,
): GuestCartStoredItem[] {
  const without = items.filter((item) => item.id !== cartItemId);
  const addOns = canonicalizeAddOns(payload.add_ons);
  const key = addOnsConfigurationKey(payload.product_variant_id, addOns);
  const existingIndex = without.findIndex(
    (item) => addOnsConfigurationKey(item.product_variant_id, item.add_ons) === key,
  );
  const displayAddOns = payload.display?.add_ons ?? [];

  if (existingIndex >= 0) {
    const next = [...without];
    next[existingIndex] = {
      ...next[existingIndex],
      quantity: Math.min(99, next[existingIndex].quantity + payload.quantity),
      add_ons: addOns,
      add_ons_display: displayAddOns.length > 0 ? displayAddOns : next[existingIndex].add_ons_display,
      product: payload.display?.product ?? next[existingIndex].product,
      variant: payload.display?.variant ?? next[existingIndex].variant,
    };

    return next;
  }

  return [
    ...without,
    {
      id: cartItemId < 0 ? cartItemId : nextGuestItemId(without),
      product_variant_id: payload.product_variant_id,
      quantity: Math.min(99, payload.quantity),
      add_ons: addOns,
      add_ons_display: displayAddOns,
      product: payload.display?.product ?? null,
      variant: payload.display?.variant ?? null,
    },
  ];
}

export function updateGuestCartItemQuantity(
  items: GuestCartStoredItem[],
  cartItemId: number,
  quantity: number,
): GuestCartStoredItem[] {
  return items
    .map((item) =>
      item.id === cartItemId
        ? { ...item, quantity: Math.min(99, Math.max(1, quantity)) }
        : item,
    )
    .filter((item) => item.quantity > 0);
}

export function removeGuestCartItem(items: GuestCartStoredItem[], cartItemId: number): GuestCartStoredItem[] {
  return items.filter((item) => item.id !== cartItemId);
}

export function guestItemsForMerge(items: GuestCartStoredItem[]): Array<{
  product_variant_id: number;
  quantity: number;
  add_ons?: CartAddOnSelection[];
}> {
  return items.map((item) => {
    const addOns = canonicalizeAddOns(item.add_ons);

    return {
      product_variant_id: item.product_variant_id,
      quantity: item.quantity,
      ...(addOns.length > 0 ? { add_ons: addOns } : {}),
    };
  });
}
