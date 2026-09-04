import { ApiEnvelope, destroy, get, post, postForm, put } from './client';
import { CartAddOnSelection } from '../types/cart';
import { canonicalizeAddOns } from '../utils/addOns';

export interface DiningTableOption {
  id: number;
  code: string;
  name: string | null;
  label: string;
  state: string;
  available: boolean;
}

export interface DiningDraftAddOn {
  add_on_id: number;
  name?: string | null;
  quantity: number;
  unit_price?: string | null;
}

export interface DiningDraftItem {
  id: number;
  product_variant_id: number;
  quantity: number;
  product_name?: string | null;
  variant_name?: string | null;
  unit_price?: string | null;
  base_line_total?: string | null;
  addon_line_total?: string | null;
  line_total?: string | null;
  add_ons?: DiningDraftAddOn[];
}

export interface DiningServiceRequest {
  id: number;
  dining_session_id: number;
  table_id: number;
  table_label?: string | null;
  type: string;
  type_label?: string;
  status: 'pending' | 'claimed' | 'completed' | 'cancelled' | string;
  status_label?: string;
  preferred_waiter_user_id?: number | null;
  claimed_by_user_id?: number | null;
  acknowledged_at?: string | null;
  escalated_at?: string | null;
  completed_at?: string | null;
  cancelled_at?: string | null;
  created_at?: string | null;
  customer_message?: string | null;
  is_escalated?: boolean;
  action_url?: string | null;
}

export interface DiningRoundItem {
  id: number;
  product_name?: string | null;
  variant_name?: string | null;
  quantity: number;
  line_subtotal?: string | null;
  add_ons?: Array<{
    add_on_id: number;
    name?: string | null;
    quantity: number;
    unit_price?: string | null;
    line_total?: string | null;
  }>;
}

export interface DiningRound {
  id: number;
  order_number?: string | null;
  dining_round_number?: number | null;
  status?: string | null;
  status_label?: string | null;
  served?: boolean;
  served_at?: string | null;
  placed_at?: string | null;
  total_amount?: string | null;
  items?: DiningRoundItem[];
}

export interface DiningSession {
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
  };
  running_bill?: {
    subtotal: string;
    discount: string;
    tax: string;
    total: string;
  } | null;
  drafts: DiningDraftItem[];
  rounds: DiningRound[];
  payment_method?: string | null;
  payment_status?: string | null;
  billing_requested_at?: string | null;
  capabilities?: {
    can_add_rounds: boolean;
    can_upload_payment_proof: boolean;
    can_call_waiter?: boolean;
  };
  service_request?: DiningServiceRequest | null;
}

export function fetchDiningTables(): Promise<ApiEnvelope<DiningTableOption[]>> {
  return get<ApiEnvelope<DiningTableOption[]>>('/dining/tables');
}

export function fetchActiveDiningSession(): Promise<ApiEnvelope<DiningSession | null>> {
  return get<ApiEnvelope<DiningSession | null>>('/dining/sessions/active');
}

export function fetchDiningSession(sessionId: number | string): Promise<ApiEnvelope<DiningSession>> {
  return get<ApiEnvelope<DiningSession>>(`/dining/sessions/${sessionId}`);
}

export function startDiningSession(payload: {
  cafe_table_id: number;
  guest_count?: number;
}): Promise<ApiEnvelope<DiningSession>> {
  return post<ApiEnvelope<DiningSession>, typeof payload>('/dining/sessions', payload);
}

export function addDiningDraft(
  sessionId: number | string,
  payload: { product_variant_id: number; quantity: number; add_ons?: CartAddOnSelection[] },
): Promise<ApiEnvelope<DiningSession>> {
  const addOns = canonicalizeAddOns(payload.add_ons);
  const body = {
    product_variant_id: payload.product_variant_id,
    quantity: payload.quantity,
    ...(addOns.length > 0 ? { add_ons: addOns } : {}),
  };

  return post<ApiEnvelope<DiningSession>, typeof body>(`/dining/sessions/${sessionId}/drafts`, body);
}

export function updateDiningDraft(
  sessionId: number | string,
  draftId: number | string,
  payload: { quantity: number },
): Promise<ApiEnvelope<DiningSession>> {
  return put<ApiEnvelope<DiningSession>, typeof payload>(
    `/dining/sessions/${sessionId}/drafts/${draftId}`,
    payload,
  );
}

export function removeDiningDraft(
  sessionId: number | string,
  draftId: number | string,
): Promise<ApiEnvelope<DiningSession>> {
  return destroy<ApiEnvelope<DiningSession>>(`/dining/sessions/${sessionId}/drafts/${draftId}`);
}

export function clearDiningDrafts(sessionId: number | string): Promise<ApiEnvelope<DiningSession>> {
  return destroy<ApiEnvelope<DiningSession>>(`/dining/sessions/${sessionId}/drafts`);
}

export function placeDiningRound(
  sessionId: number | string,
  payload: { customer_notes?: string } = {},
): Promise<ApiEnvelope<DiningSession>> {
  return post<ApiEnvelope<DiningSession>, typeof payload>(`/dining/sessions/${sessionId}/rounds`, payload);
}

export function callWaiter(sessionId: number | string): Promise<ApiEnvelope<DiningServiceRequest>> {
  return post<ApiEnvelope<DiningServiceRequest>, Record<string, never>>(
    `/dining/sessions/${sessionId}/service-requests`,
    {},
  );
}

export function fetchCurrentWaiterCall(
  sessionId: number | string,
): Promise<ApiEnvelope<DiningServiceRequest | null>> {
  return get<ApiEnvelope<DiningServiceRequest | null>>(`/dining/sessions/${sessionId}/service-requests/current`);
}

export function cancelWaiterCall(
  serviceRequestId: number | string,
): Promise<ApiEnvelope<DiningServiceRequest>> {
  return post<ApiEnvelope<DiningServiceRequest>, Record<string, never>>(
    `/dining/service-requests/${serviceRequestId}/cancel`,
    {},
  );
}

export function requestDiningBill(sessionId: number | string): Promise<ApiEnvelope<DiningSession>> {
  return post<ApiEnvelope<DiningSession>, Record<string, never>>(`/dining/sessions/${sessionId}/request-bill`, {});
}

export function setDiningPaymentMethod(
  sessionId: number | string,
  paymentMethod: 'cash' | 'manual_upi',
): Promise<ApiEnvelope<DiningSession>> {
  return post<ApiEnvelope<DiningSession>, { payment_method: string }>(
    `/dining/sessions/${sessionId}/payment-method`,
    { payment_method: paymentMethod },
  );
}

export function uploadDiningPaymentProof(
  sessionId: number | string,
  file: File,
): Promise<ApiEnvelope<DiningSession>> {
  const body = new FormData();
  body.append('payment_proof', file);

  return postForm<ApiEnvelope<DiningSession>>(`/dining/sessions/${sessionId}/payment-proof`, body);
}
