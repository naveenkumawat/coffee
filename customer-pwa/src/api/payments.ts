import { post } from './client';

export interface PaymentInitiationResponse {
  data: {
    attempt_id: number;
    provider: string;
    status: string | null;
    client: Record<string, unknown>;
  };
}

export async function initiateOrderPayment(
  orderId: number | string,
  paymentMethod: string,
): Promise<PaymentInitiationResponse> {
  return post<PaymentInitiationResponse, { payment_method: string }>(
    `/orders/${orderId}/payment/initiate`,
    { payment_method: paymentMethod },
  );
}

export async function verifyPaymentReturn(
  attemptId: number | string,
  payload: Record<string, unknown>,
): Promise<{ data: { status: string | null; order_status: string | null; payment_status: string | null } }> {
  return post(`/payment-attempts/${attemptId}/verify-return`, payload);
}
