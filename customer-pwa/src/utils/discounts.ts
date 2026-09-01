import { CartDiscount, CartSummary } from '../types/cart';
import { Order, OrderPromotion } from '../types/order';

export function discountAmount(value: string | number | null | undefined): number {
  const amount = Number(value ?? 0);

  return Number.isNaN(amount) ? 0 : amount;
}

export function hasDiscountSavings(discountTotal: string | number | null | undefined): boolean {
  return discountAmount(discountTotal) > 0;
}

export function cartDiscounts(summary: CartSummary | null | undefined): CartDiscount[] {
  return summary?.discounts ?? [];
}

/**
 * Normalize order promotions from either a dedicated promotions[] payload
 * or fall back to a single line derived from discount_total.
 */
export function orderDiscountLines(order: Order): OrderPromotion[] {
  const promotions = order.promotions ?? [];

  if (promotions.length > 0) {
    return promotions.map((promotion) => ({
      name: promotion.name,
      code: promotion.code ?? null,
      amount: promotion.amount,
    }));
  }

  if (hasDiscountSavings(order.discount_total)) {
    return [
      {
        name: 'Discount',
        code: null,
        amount: order.discount_total,
      },
    ];
  }

  return [];
}
