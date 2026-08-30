import { ApiEnvelope } from '../api/client';

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
}

export interface OrderStatusTimelineItem {
  id: number;
  from_status: string | null;
  from_status_label: string | null;
  to_status: string | null;
  to_status_label: string | null;
  created_at: string | null;
}

export interface Order {
  id: number;
  order_number: string;
  status: string | null;
  status_label: string | null;
  customer_name: string;
  customer_email: string;
  customer_phone: string;
  pickup_name: string;
  pickup_phone: string;
  customer_notes: string | null;
  pickup_notes: string | null;
  subtotal: string;
  discount_total: string;
  total_amount: string;
  placed_at: string | null;
  payment_confirmed_at: string | null;
  accepted_at: string | null;
  preparing_at: string | null;
  ready_for_pickup_at: string | null;
  completed_at: string | null;
  cancelled_at: string | null;
  rejected_at: string | null;
  items: OrderItem[];
  status_timeline: OrderStatusTimelineItem[];
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

export interface OrderPaymentInstructions {
  display_name: string | null;
  instructions: string | null;
  upi_id: string | null;
  whatsapp_number: string | null;
}

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
