import { ApiEnvelope, get } from './client';
import { HomePayload } from '../types/home';

export interface FetchHomeOptions {
  placement?: 'home' | 'menu';
  visitor_key?: string | null;
  session_key?: string | null;
  cart_product_ids?: number[];
  fulfilment_method?: string | null;
}

export function fetchHome(options: FetchHomeOptions = {}): Promise<ApiEnvelope<HomePayload>> {
  const params = new URLSearchParams();

  if (options.placement) {
    params.set('placement', options.placement);
  }

  if (options.visitor_key) {
    params.set('visitor_key', options.visitor_key);
  }

  if (options.session_key) {
    params.set('session_key', options.session_key);
  }

  if (options.fulfilment_method) {
    params.set('fulfilment_method', options.fulfilment_method);
  }

  for (const id of options.cart_product_ids ?? []) {
    params.append('cart_product_ids[]', String(id));
  }

  const query = params.toString();

  return get<ApiEnvelope<HomePayload>>(`/home${query ? `?${query}` : ''}`);
}
