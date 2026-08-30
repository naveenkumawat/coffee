import { ApiEnvelope, get } from './client';
import { OrderDetailResponse, OrderListResponse } from '../types/order';

export function fetchOrders(page = 1, perPage = 20): Promise<OrderListResponse> {
  const params = new URLSearchParams({
    page: String(page),
    per_page: String(perPage)
  });

  return get<OrderListResponse>(`/orders?${params.toString()}`);
}

export function fetchOrder(orderId: number | string): Promise<OrderDetailResponse> {
  return get<OrderDetailResponse>(`/orders/${orderId}`);
}
