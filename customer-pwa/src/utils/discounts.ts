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
 * Customer-facing discount label from backend name/code — never invents promotion names.
 */
export function discountDisplayLabel(discount: {
  name?: string | null;
  code?: string | null;
}): string {
  const name = (discount.name ?? '').trim() || 'Discount';
  const code = (discount.code ?? '').trim();

  if (code && !name.includes(code)) {
    return `${name} (${code})`;
  }

  return name;
}

/**
 * Normalize order discount lines from backend discounts[], promotions[], or
 * fall back to a single line derived from discount_total when no snapshot exists.
 */
export function orderDiscountLines(order: Order): OrderPromotion[] {
  const structured = order.discounts ?? [];

  if (structured.length > 0) {
    return structured.map((discount) => ({
      name: discount.name,
      code: discount.code ?? null,
      amount: discount.amount,
      type: discount.type,
    }));
  }

  const promotions = order.promotions ?? [];

  if (promotions.length > 0) {
    return promotions.map((promotion) => ({
      name: promotion.name,
      code: promotion.code ?? null,
      amount: promotion.amount,
      type: 'promotion',
    }));
  }

  const referralCoupons = (order.reward_redemptions ?? [])
    .filter((redemption) => redemption.reward_type === 'coupon' && discountAmount(redemption.benefit_amount) > 0)
    .map((redemption) => ({
      name: redemption.description || 'Referral Reward',
      code: redemption.coupon_code ?? null,
      amount: redemption.benefit_amount,
      type: 'referral',
    }));

  if (referralCoupons.length > 0) {
    return referralCoupons;
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

/**
 * Dining bill discount lines from backend totals/final_bill/discounts snapshots.
 */
export function diningDiscountLines(session: {
  discounts?: Array<{ name: string; code?: string | null; amount: string; type?: string }>;
  totals?: { discounts?: Array<{ name: string; code?: string | null; amount: string; type?: string }>; discount?: string };
  final_bill?: { discounts?: Array<{ name: string; code?: string | null; amount: string; type?: string }>; discount?: string } | null;
  promotions?: Array<{ name: string; code?: string | null; amount: string }>;
}): OrderPromotion[] {
  const fromBill =
    session.final_bill?.discounts
    ?? session.totals?.discounts
    ?? session.discounts
    ?? [];

  if (fromBill.length > 0) {
    return fromBill.map((discount) => ({
      name: discount.name,
      code: discount.code ?? null,
      amount: discount.amount,
      type: discount.type,
    }));
  }

  const promotions = session.promotions ?? [];
  if (promotions.length > 0) {
    return promotions.map((promotion) => ({
      name: promotion.name,
      code: promotion.code ?? null,
      amount: promotion.amount,
      type: 'promotion',
    }));
  }

  const aggregate = session.final_bill?.discount ?? session.totals?.discount;
  if (hasDiscountSavings(aggregate)) {
    return [
      {
        name: 'Discount',
        code: null,
        amount: String(aggregate),
      },
    ];
  }

  return [];
}
