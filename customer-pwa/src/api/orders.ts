import { downloadFile, get, post, postForm } from './client';
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

export function cancelOrder(orderId: number | string): Promise<OrderDetailResponse> {
  return post<OrderDetailResponse, Record<string, never>>(`/orders/${orderId}/cancel`, {});
}

export function uploadPaymentProof(orderId: number | string, file: File): Promise<OrderDetailResponse> {
  const body = new FormData();
  body.append('payment_proof', file);

  return postForm<OrderDetailResponse>(`/orders/${orderId}/payment-proof`, body);
}

export function submitPaymentTransactionId(
  orderId: number | string,
  transactionId: string,
): Promise<OrderDetailResponse> {
  return post<OrderDetailResponse, { transaction_id: string }>(`/orders/${orderId}/payment-proof`, {
    transaction_id: transactionId,
  });
}

export function paymentProofUrl(orderId: number | string): string {
  return `/orders/${orderId}/payment-proof`;
}

export async function downloadOrderInvoice(orderId: number | string, orderNumber: string): Promise<void> {
  await downloadFile(`/orders/${orderId}/invoice`, `Invoice-${orderNumber}.pdf`);
}
