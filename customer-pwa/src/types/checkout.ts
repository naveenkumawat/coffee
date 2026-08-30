import { ApiEnvelope } from '../api/client';
import { Cart, CartSummary } from './cart';
import { Order } from './order';

export interface CheckoutCustomerDefaults {
  name: string;
  email: string;
  phone: string | null;
}

export interface CheckoutPaymentInstructions {
  display_name: string | null;
  instructions: string | null;
  upi_id: string | null;
  whatsapp_number: string | null;
}

export interface CheckoutSummaryMeta extends Record<string, unknown> {
  summary: CartSummary;
  checkout_token: string;
  customer: CheckoutCustomerDefaults;
}

export interface CheckoutSummaryResponse extends ApiEnvelope<Cart> {
  meta: CheckoutSummaryMeta;
}

export interface CheckoutSubmitPayload {
  checkout_token: string;
  customer_name: string;
  customer_email: string;
  customer_phone: string;
  pickup_name: string;
  pickup_phone: string;
  customer_notes?: string | null;
  pickup_notes?: string | null;
}

export interface CheckoutSubmitResponse extends ApiEnvelope<Order> {
  meta: {
    payment: CheckoutPaymentInstructions;
  };
}
