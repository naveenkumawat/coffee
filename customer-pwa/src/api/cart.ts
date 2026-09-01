import { ApiEnvelope, destroy, get, post, put } from './client';
import { Cart, CartCountResponse, CartItemMutationPayload, CartMergePayload } from '../types/cart';
import { CheckoutFulfilmentMethod } from '../types/checkout';

export function fetchCart(): Promise<ApiEnvelope<Cart>> {
  return get<ApiEnvelope<Cart>>('/cart');
}

export interface ApplyCartPromoCodePayload {
  promo_code: string;
  fulfilment_method?: CheckoutFulfilmentMethod;
}

export function applyCartPromoCode(payload: ApplyCartPromoCodePayload): Promise<ApiEnvelope<Cart>> {
  return post<ApiEnvelope<Cart>, ApplyCartPromoCodePayload>('/cart/promo-code', payload);
}

export function clearCartPromoCode(): Promise<ApiEnvelope<Cart>> {
  return destroy<ApiEnvelope<Cart>>('/cart/promo-code');
}

export function fetchActiveRewards(): Promise<ApiEnvelope<{ rewards: Array<{
  id: number;
  reward_type: string;
  title: string;
  coupon_code: string | null;
  expires_at: string | null;
  product_id: number | null;
  variant_id: number | null;
  quantity: number | null;
}> }>> {
  return get('/customer/rewards');
}

export function applyFreeDrinkReward(payload: {
  reward_id: number;
  fulfilment_method?: CheckoutFulfilmentMethod;
}): Promise<ApiEnvelope<Cart>> {
  return post('/cart/referral-rewards/free-drink', payload);
}

export function applyReferralCouponReward(payload: {
  referral_coupon: string;
  fulfilment_method?: CheckoutFulfilmentMethod;
}): Promise<ApiEnvelope<Cart>> {
  return post('/cart/referral-rewards/coupon', payload);
}

export function clearReferralRewards(fulfilmentMethod?: CheckoutFulfilmentMethod | null): Promise<ApiEnvelope<Cart>> {
  const query = fulfilmentMethod ? `?fulfilment_method=${encodeURIComponent(fulfilmentMethod)}` : '';
  return destroy(`/cart/referral-rewards${query}`);
}

export function fetchReferralSummary(): Promise<ApiEnvelope<{
  enabled: boolean;
  referral_code: string;
  share_url: string;
  customer_message: string | null;
  stats: {
    successful_referrals: number;
    available_rewards: number;
    redeemed_rewards: number;
    expired_rewards: number;
  };
}>> {
  return get('/customer/referral');
}

export function fetchCartCount(): Promise<ApiEnvelope<CartCountResponse>> {
  return get<ApiEnvelope<CartCountResponse>>('/cart/count');
}

export function addCartItem(payload: CartItemMutationPayload): Promise<ApiEnvelope<Cart>> {
  return post<ApiEnvelope<Cart>, { product_variant_id: number; quantity: number }>('/cart/items', {
    product_variant_id: payload.product_variant_id,
    quantity: payload.quantity,
  });
}

export function updateCartItem(cartItemId: number, payload: { quantity: number }): Promise<ApiEnvelope<Cart>> {
  return put<ApiEnvelope<Cart>, { quantity: number }>(`/cart/items/${cartItemId}`, payload);
}

export function removeCartItem(cartItemId: number): Promise<ApiEnvelope<Cart>> {
  return destroy<ApiEnvelope<Cart>>(`/cart/items/${cartItemId}`);
}

export function clearCart(): Promise<ApiEnvelope<Cart>> {
  return destroy<ApiEnvelope<Cart>>('/cart');
}

export function mergeGuestCart(payload: CartMergePayload): Promise<ApiEnvelope<Cart>> {
  return post<ApiEnvelope<Cart>, CartMergePayload>('/cart/merge', payload);
}
