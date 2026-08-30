import { ApiEnvelope, get, post } from './client';
import { CheckoutSubmitPayload, CheckoutSubmitResponse, CheckoutSummaryResponse } from '../types/checkout';
import { Order } from '../types/order';

export function fetchCheckoutSummary(): Promise<CheckoutSummaryResponse> {
  return get<CheckoutSummaryResponse>('/checkout/summary');
}

export function submitCheckout(payload: CheckoutSubmitPayload): Promise<CheckoutSubmitResponse> {
  return post<CheckoutSubmitResponse, CheckoutSubmitPayload>('/checkout', payload);
}

export function fetchOrder(orderId: number | string): Promise<ApiEnvelope<Order>> {
  return get<ApiEnvelope<Order>>(`/orders/${orderId}`);
}
