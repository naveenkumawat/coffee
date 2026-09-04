import { ApiEnvelope, post } from './client';

export type BehaviourEventType =
  | 'product_viewed'
  | 'category_viewed'
  | 'search_performed'
  | 'product_customized'
  | 'cart_item_added'
  | 'cart_item_removed'
  | 'checkout_started'
  | 'favourite_added'
  | 'favourite_removed'
  | 'recommendation_impression'
  | 'recommendation_clicked'
  | 'campaign_impression'
  | 'campaign_clicked'
  | 'campaign_dismissed'
  | 'loyalty_reward_viewed'
  | 'loyalty_reward_selected';

export interface BehaviourEventPayload {
  event_type: BehaviourEventType;
  visitor_key: string;
  product_id?: number;
  product_category_id?: number;
  product_variant_id?: number;
  page_context?: string;
  metadata?: Record<string, unknown>;
  occurred_at?: string;
  idempotency_key?: string;
}

export interface BehaviourIngestResult {
  accepted: boolean;
  reason?: string;
  event_id?: number | null;
}

export interface BehaviourMergeResult {
  merged: boolean;
  attached: number;
  reason?: string;
}

export function ingestBehaviourEvent(
  payload: BehaviourEventPayload,
): Promise<ApiEnvelope<BehaviourIngestResult>> {
  return post<ApiEnvelope<BehaviourIngestResult>, BehaviourEventPayload>('/behaviour/events', payload);
}

export function mergeBehaviourVisitor(
  visitorKey: string,
): Promise<ApiEnvelope<BehaviourMergeResult>> {
  return post<ApiEnvelope<BehaviourMergeResult>, { visitor_key: string }>('/behaviour/merge', {
    visitor_key: visitorKey,
  });
}
