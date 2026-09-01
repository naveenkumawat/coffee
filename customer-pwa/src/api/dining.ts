import { ApiEnvelope, destroy, get, post, postForm, put } from './client';

export interface DiningTableOption {
  id: number;
  code: string;
  name: string | null;
  label: string;
  state: string;
  available: boolean;
}

export interface DiningDraftItem {
  id: number;
  product_variant_id: number;
  quantity: number;
  product_name?: string | null;
  variant_name?: string | null;
  unit_price?: string | null;
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
  };
  drafts: DiningDraftItem[];
  rounds: Array<Record<string, unknown>>;
  payment_method?: string | null;
  payment_status?: string | null;
  capabilities?: {
    can_add_rounds: boolean;
    can_upload_payment_proof: boolean;
  };
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
  payload: { product_variant_id: number; quantity: number },
): Promise<ApiEnvelope<DiningSession>> {
  return post<ApiEnvelope<DiningSession>, typeof payload>(`/dining/sessions/${sessionId}/drafts`, payload);
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
