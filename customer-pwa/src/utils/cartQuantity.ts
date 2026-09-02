import { Cart, CartAddOnSelection, CartItem, CartItemAddOn, CartSummary } from '../types/cart';
import { addOnsConfigurationKey, canonicalizeAddOns } from './addOns';

export const CART_MAX_QUANTITY = 99;

export function totalCartQuantity(cart: Cart | null, summary: CartSummary | null): number {
  if (summary?.item_count != null) {
    return summary.item_count;
  }

  return cart?.items.reduce((carry, item) => carry + item.quantity, 0) ?? 0;
}

export function findCartItemByVariantId(cart: Cart | null, productVariantId: number): CartItem | null {
  return findCartItemByConfiguration(cart, productVariantId, []);
}

export function findCartItemByConfiguration(
  cart: Cart | null,
  productVariantId: number,
  addOns: Array<CartAddOnSelection | CartItemAddOn> | null | undefined = [],
): CartItem | null {
  const key = addOnsConfigurationKey(productVariantId, addOns);

  return (
    cart?.items.find((item) => {
      if (item.variant?.id !== productVariantId) {
        return false;
      }

      return addOnsConfigurationKey(productVariantId, item.add_ons) === key;
    }) ?? null
  );
}

export function quantityForVariant(
  cart: Cart | null,
  productVariantId: number,
  addOns: Array<CartAddOnSelection | CartItemAddOn> | null | undefined = [],
): number {
  return findCartItemByConfiguration(cart, productVariantId, addOns)?.quantity ?? 0;
}

export function selectionFromCartItem(item: CartItem): CartAddOnSelection[] {
  return canonicalizeAddOns(item.add_ons);
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
