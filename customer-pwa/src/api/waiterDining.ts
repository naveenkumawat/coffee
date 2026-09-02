import { ApiEnvelope, destroy, get, post, put } from './client';
import { CartAddOnSelection } from '../types/cart';

export type WaiterTableDisplayState =
  | 'available'
  | 'active'
  | 'preparing'
  | 'ready_to_serve'
  | 'bill_requested'
  | 'payment_pending'
  | 'inactive';

export interface WaiterTableSessionSummary {
  id: number;
  session_number: string;
  status: string;
  status_label?: string;
  guest_count?: number | null;
  round_count: number;
  has_unsent_draft: boolean;
  draft_item_count: number;
  running_total?: string | null;
  ready_to_serve: boolean;
  is_preparing: boolean;
  station_summary?: Array<{
    station: string;
    station_label?: string;
    status: string;
    status_label?: string;
  }>;
}

export interface WaiterTable {
  id: number;
  code: string;
  name: string | null;
  label: string;
  state: string;
  display_state: WaiterTableDisplayState;
  display_state_label: string;
  available: boolean;
  session: WaiterTableSessionSummary | null;
}

export interface WaiterDraftAddOn {
  add_on_id: number;
  name?: string | null;
  quantity: number;
  unit_price?: string | null;
}

export interface WaiterDraftItem {
  id: number;
  product_variant_id: number;
  quantity: number;
  product_name?: string | null;
  variant_name?: string | null;
  unit_price?: string | null;
  base_line_total?: string | null;
  addon_line_total?: string | null;
  line_total?: string | null;
  add_ons?: WaiterDraftAddOn[];
}

export interface WaiterRoundItem {
  id: number;
  product_name: string;
  variant_name?: string | null;
  quantity: number;
  unit_price?: string | null;
  line_subtotal?: string | null;
  preparation_station?: string | null;
  add_ons?: Array<{
    name: string;
    quantity: number;
    unit_price?: string | null;
    total_price?: string | null;
  }>;
}

export interface WaiterRound {
  id: number;
  order_number: string;
  round_number: number;
  status: string;
  status_label?: string;
  placed_at?: string | null;
  subtotal?: string | null;
  total_amount?: string | null;
  ready_to_serve: boolean;
  is_preparing: boolean;
  stations: Array<{
    station?: string | null;
    station_label?: string | null;
    status?: string | null;
    status_label?: string | null;
  }>;
  items: WaiterRoundItem[];
}

export interface WaiterDiningSession {
  id: number;
  session_number: string;
  status: string;
  status_label?: string;
  guest_count?: number | null;
  table: { id: number; label: string };
  totals: {
    subtotal: string;
    discount: string;
    tax: string;
    total: string;
    finalized?: boolean;
  };
  running_bill?: {
    subtotal: string;
    discount: string;
    tax: string;
    total: string;
  } | null;
  final_bill?: {
    subtotal: string;
    discount: string;
    tax: string;
    total: string;
  } | null;
  drafts: WaiterDraftItem[];
  rounds: WaiterRound[];
  payment_method?: string | null;
  payment_status?: string | null;
  ready_to_serve_round_ids?: number[];
  capabilities?: {
    can_add_rounds?: boolean;
    can_request_bill?: boolean;
    can_change_payment_method?: boolean;
    can_mark_cash_received?: boolean;
    can_close?: boolean;
    can_reopen?: boolean;
    can_confirm_upi?: boolean;
    has_unsent_draft?: boolean;
    draft_item_count?: number;
  };
}

export function fetchWaiterTables(): Promise<ApiEnvelope<WaiterTable[]>> {
  return get<ApiEnvelope<WaiterTable[]>>('/waiter/tables');
}

export function fetchWaiterSession(sessionId: number | string): Promise<ApiEnvelope<WaiterDiningSession>> {
  return get<ApiEnvelope<WaiterDiningSession>>(`/waiter/sessions/${sessionId}`);
}

export function startWaiterSession(payload: {
  cafe_table_id: number;
  guest_count?: number;
}): Promise<ApiEnvelope<WaiterDiningSession>> {
  return post<ApiEnvelope<WaiterDiningSession>, typeof payload>('/waiter/sessions', payload);
}

export function addWaiterDraft(
  sessionId: number | string,
  payload: { product_variant_id: number; quantity: number; add_ons?: CartAddOnSelection[] },
): Promise<ApiEnvelope<WaiterDiningSession>> {
  return post<ApiEnvelope<WaiterDiningSession>, typeof payload>(
    `/waiter/sessions/${sessionId}/drafts`,
    payload,
  );
}

export function updateWaiterDraft(
  sessionId: number | string,
  draftId: number | string,
  payload: {
    quantity: number;
    product_variant_id?: number;
    add_ons?: CartAddOnSelection[];
  },
): Promise<ApiEnvelope<WaiterDiningSession>> {
  return put<ApiEnvelope<WaiterDiningSession>, typeof payload>(
    `/waiter/sessions/${sessionId}/drafts/${draftId}`,
    payload,
  );
}

export function removeWaiterDraft(
  sessionId: number | string,
  draftId: number | string,
): Promise<ApiEnvelope<WaiterDiningSession>> {
  return destroy<ApiEnvelope<WaiterDiningSession>>(`/waiter/sessions/${sessionId}/drafts/${draftId}`);
}

export function clearWaiterDrafts(sessionId: number | string): Promise<ApiEnvelope<WaiterDiningSession>> {
  return destroy<ApiEnvelope<WaiterDiningSession>>(`/waiter/sessions/${sessionId}/drafts`);
}

export function placeWaiterRound(
  sessionId: number | string,
  payload: { customer_notes?: string; idempotency_key: string },
): Promise<ApiEnvelope<WaiterDiningSession>> {
  return post<ApiEnvelope<WaiterDiningSession>, typeof payload>(
    `/waiter/sessions/${sessionId}/rounds`,
    payload,
  );
}

export function requestWaiterBill(
  sessionId: number | string,
  payload: { discard_draft?: boolean } = {},
): Promise<ApiEnvelope<WaiterDiningSession>> {
  return post<ApiEnvelope<WaiterDiningSession>, typeof payload>(
    `/waiter/sessions/${sessionId}/request-bill`,
    payload,
  );
}

export function setWaiterPaymentMethod(
  sessionId: number | string,
  paymentMethod: 'cash' | 'manual_upi',
): Promise<ApiEnvelope<WaiterDiningSession>> {
  return post<ApiEnvelope<WaiterDiningSession>, { payment_method: string }>(
    `/waiter/sessions/${sessionId}/payment-method`,
    { payment_method: paymentMethod },
  );
}

export function markWaiterCashReceived(
  sessionId: number | string,
): Promise<ApiEnvelope<WaiterDiningSession>> {
  return post<ApiEnvelope<WaiterDiningSession>, Record<string, never>>(
    `/waiter/sessions/${sessionId}/cash`,
    {},
  );
}

export function closeWaiterSession(sessionId: number | string): Promise<ApiEnvelope<WaiterDiningSession>> {
  return post<ApiEnvelope<WaiterDiningSession>, Record<string, never>>(
    `/waiter/sessions/${sessionId}/close`,
    {},
  );
}

export function reopenWaiterSession(
  sessionId: number | string,
  note?: string,
): Promise<ApiEnvelope<WaiterDiningSession>> {
  return post<ApiEnvelope<WaiterDiningSession>, { note?: string }>(
    `/waiter/sessions/${sessionId}/reopen`,
    note ? { note } : {},
  );
}
