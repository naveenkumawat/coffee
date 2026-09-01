import { get, post } from './client';
import {
  CheckoutFulfilmentMethod,
  CheckoutSubmitPayload,
  CheckoutSubmitResponse,
  CheckoutSummaryResponse
} from '../types/checkout';

export function fetchCheckoutSummary(
  fulfilmentMethod?: CheckoutFulfilmentMethod | null,
): Promise<CheckoutSummaryResponse> {
  const query = fulfilmentMethod
    ? `?fulfilment_method=${encodeURIComponent(fulfilmentMethod)}`
    : '';

  return get<CheckoutSummaryResponse>(`/checkout/summary${query}`);
}

export function submitCheckout(payload: CheckoutSubmitPayload): Promise<CheckoutSubmitResponse> {
  return post<CheckoutSubmitResponse, CheckoutSubmitPayload>('/checkout', payload);
}
