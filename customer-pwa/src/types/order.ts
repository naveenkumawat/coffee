import { ApiEnvelope } from '../api/client';
import { CheckoutFulfilmentMethod, CheckoutPaymentInstructions } from './checkout';

export interface OrderItemAddOn {
  add_on_id: number;
  name: string | null;
  quantity: number;
  unit_price: string | null;
  line_total: string | null;
}

export interface OrderItem {
  id: number;
  product_id: number | null;
  product_variant_id: number | null;
  product_name: string;
  variant_name: string | null;
  customer_ingredient_summary: string | null;
  unit_price: string | null;
  quantity: number;
  line_subtotal: string | null;
  add_ons?: OrderItemAddOn[];
  my_rating?: {
    id: number;
    rating: number;
    review: string | null;
    created_at: string | null;
    updated_at: string | null;
  } | null;
  can_rate?: boolean;
}

export interface OrderStatusTimelineItem {
  id: number;
  from_status: string | null;
  from_status_label: string | null;
  to_status: string | null;
  to_status_label: string | null;
  created_at: string | null;
}

export interface OrderPaymentProof {
  uploaded: boolean;
  uploaded_at: string | null;
  mime: string | null;
  size: number | null;
  can_upload: boolean;
  rejection_notes: string | null;
}

export interface OrderPromotion {
  name: string;
  code?: string | null;
  amount: string;
}

export interface Order {
  id: number;
  order_number: string;
  status: string | null;
  status_label: string | null;
  fulfilment_method: CheckoutFulfilmentMethod | 'dine_in' | null;
  fulfilment_method_label: string | null;
  cafe_table_id: number | null;
  table_name: string | null;
  customer_name: string;
  customer_email: string;
  customer_phone: string;
  pickup_name: string | null;
  pickup_phone: string | null;
  customer_notes: string | null;
  pickup_notes: string | null;
  delivery_address: string | null;
  delivery_phone: string | null;
  delivery_contact_name: string | null;
  delivery_notes: string | null;
  delivery_provider: string | null;
  delivery_fee_amount: string | null;
  delivery_tracking_reference: string | null;
  delivery_status: string | null;
  delivery_disclaimer: string | null;
  subtotal: string;
  discount_total: string;
  /** Present when the API includes applied promotions on the order payload. */
  promotions?: OrderPromotion[];
  reward_redemptions?: Array<{
    reward_type: string | null;
    description: string | null;
    benefit_amount: string;
    original_amount?: string | null;
    preserves_gst_basis?: boolean;
    coupon_code?: string | null;
  }>;
  total_amount: string;
  tax: {
    enabled: boolean;
    label: string;
    percent: string;
    inclusive: boolean;
    taxable_amount: string;
    amount: string;
  } | null;
  payment_method: string | null;
  payment_method_label: string | null;
  payment_status: string | null;
  payment_status_label: string | null;
  payment_reference: string | null;
  payment_proof: OrderPaymentProof | null;
  placed_at: string | null;
  payment_confirmed_at: string | null;
  cash_received_at: string | null;
  accepted_at: string | null;
  preparing_at: string | null;
  ready_for_pickup_at: string | null;
  completed_at: string | null;
  cancelled_at: string | null;
  rejected_at: string | null;
  items: OrderItem[];
  status_timeline: OrderStatusTimelineItem[];
  invoice_available: boolean;
}

export type OrderStatusValue =
  | 'pending_payment'
  | 'payment_confirmed'
  | 'accepted'
  | 'preparing'
  | 'ready_for_pickup'
  | 'completed'
  | 'cancelled'
  | 'rejected';

export type OrderPaymentInstructions = CheckoutPaymentInstructions;

export interface OrderListResponse extends ApiEnvelope<Order[]> {
  meta?: {
    pagination?: {
      current_page: number;
      last_page: number;
      per_page: number;
      total: number;
      from: number | null;
      to: number | null;
    };
  };
}

export interface OrderDetailResponse extends ApiEnvelope<Order> {
  meta?: {
    payment?: OrderPaymentInstructions;
  };
}
