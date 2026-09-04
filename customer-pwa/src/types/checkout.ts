import { ApiEnvelope } from '../api/client';
import { Cart, CartSummary } from './cart';
import { Order } from './order';

export interface CheckoutPaymentMethodOption {
  key: string;
  code?: string;
  label: string;
  name?: string;
  subtitle?: string;
  type?: 'online' | 'manual' | string;
  available?: boolean;
  requires_initiation?: boolean;
  requires_payment_proof?: boolean;
  client_config?: Record<string, unknown>;
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
export type CheckoutPaymentMethod = string;

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
  delivery_addresses?: Array<{
    id: number;
    label: string | null;
    recipient_name: string;
    phone: string;
    address_line_1: string;
    address_line_2: string | null;
    landmark: string | null;
    city: string;
    state: string;
    postal_code: string;
    is_default: boolean;
    formatted_address: string;
  }>;
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
  delivery_address_id?: number | null;
  delivery_address?: string | null;
  delivery_phone?: string | null;
  delivery_contact_name?: string | null;
  delivery_notes?: string | null;
  address_label?: string | null;
  address_line_1?: string | null;
  address_line_2?: string | null;
  landmark?: string | null;
  city?: string | null;
  state?: string | null;
  postal_code?: string | null;
  save_delivery_address?: boolean;
  make_default_address?: boolean;
  cafe_table_id?: number | null;
}

export interface CheckoutSubmitResponse extends ApiEnvelope<Order> {
  meta: {
    payment: CheckoutPaymentInstructions | null;
  };
}
