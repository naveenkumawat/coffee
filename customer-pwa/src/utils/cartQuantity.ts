import { Cart, CartItem, CartSummary } from '../types/cart';

export const CART_MAX_QUANTITY = 99;

export function totalCartQuantity(cart: Cart | null, summary: CartSummary | null): number {
  if (summary?.item_count != null) {
    return summary.item_count;
  }

  return cart?.items.reduce((carry, item) => carry + item.quantity, 0) ?? 0;
}

export function findCartItemByVariantId(cart: Cart | null, productVariantId: number): CartItem | null {
  return cart?.items.find((item) => item.variant?.id === productVariantId) ?? null;
}

export function quantityForVariant(cart: Cart | null, productVariantId: number): number {
  return findCartItemByVariantId(cart, productVariantId)?.quantity ?? 0;
}

export function formatCartBadgeCount(count: number): string {
  if (count <= 0) {
    return '';
  }

  return count > CART_MAX_QUANTITY ? `${CART_MAX_QUANTITY}+` : String(count);
}

export function cartBadgeAriaLabel(count: number): string {
  if (count <= 0) {
    return 'Cart';
  }

  return `Cart, ${count} items`;
}
