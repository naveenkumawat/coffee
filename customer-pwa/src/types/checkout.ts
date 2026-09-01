import { ApiEnvelope } from '../api/client';
import { Cart, CartSummary } from './cart';
import { Order } from './order';

export interface CheckoutPaymentMethodOption {
  key: 'manual_upi' | 'cash' | string;
  label: string;
  subtitle?: string;
}

export interface CheckoutCustomerDefaults {
  name: string;
  email: string;
  phone: string | null;
}

export interface CheckoutPaymentInstructions {
  display_name: string | null;
  instructions: string | null;
  upi_id: string | null;
  phone: string | null;
  qr_image_path: string | null;
  whatsapp_number: string | null;
}

export type CheckoutFulfilmentMethod = 'takeaway' | 'delivery';
export type CheckoutPaymentMethod = 'manual_upi' | 'cash';

export interface CheckoutFulfilmentMeta {
  methods: Array<{ value: CheckoutFulfilmentMethod; label: string }>;
  pickup_address: string | null;
  delivery_disclaimer: string;
  dine_in_enabled?: boolean;
  dining_enabled?: boolean;
}

export interface CheckoutSummaryMeta extends Record<string, unknown> {
  summary: CartSummary;
  checkout_token: string;
  customer: CheckoutCustomerDefaults;
  fulfilment?: CheckoutFulfilmentMeta;
  payment_methods?: Partial<Record<CheckoutFulfilmentMethod, CheckoutPaymentMethodOption[]>>;
  payment?: CheckoutPaymentInstructions;
}

export interface CheckoutSummaryResponse extends ApiEnvelope<Cart> {
  meta: CheckoutSummaryMeta;
}

export interface CheckoutSubmitPayload {
  checkout_token: string;
  fulfilment_method: CheckoutFulfilmentMethod;
  payment_method: CheckoutPaymentMethod;
  customer_name: string;
  customer_email: string;
  customer_phone: string;
  pickup_name?: string | null;
  pickup_phone?: string | null;
  customer_notes?: string | null;
  pickup_notes?: string | null;
  delivery_address?: string | null;
  delivery_phone?: string | null;
  delivery_contact_name?: string | null;
  delivery_notes?: string | null;
  cafe_table_id?: number | null;
}

export interface CheckoutSubmitResponse extends ApiEnvelope<Order> {
  meta: {
    payment: CheckoutPaymentInstructions | null;
  };
}
