import { ApiEnvelope, destroy, get, post, put } from './client';
import { Cart, CartCountResponse, CartItemMutationPayload } from '../types/cart';

export function fetchCart(): Promise<ApiEnvelope<Cart>> {
  return get<ApiEnvelope<Cart>>('/cart');
}

export function fetchCartCount(): Promise<ApiEnvelope<CartCountResponse>> {
  return get<ApiEnvelope<CartCountResponse>>('/cart/count');
}

export function addCartItem(payload: CartItemMutationPayload): Promise<ApiEnvelope<Cart>> {
  return post<ApiEnvelope<Cart>, CartItemMutationPayload>('/cart/items', payload);
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
