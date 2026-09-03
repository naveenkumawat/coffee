import { ApiEnvelope, get, post } from './client';

export type CampaignPlacement =
  | 'global'
  | 'home'
  | 'menu'
  | 'category'
  | 'product_detail'
  | 'cart'
  | 'checkout'
  | 'order_success';

export type CampaignCtaType = 'product' | 'category' | 'internal_page' | 'promotion' | 'close';

export interface CampaignTrigger {
  type: 'immediate' | 'delay' | 'scroll' | 'product_views';
  delay_ms?: number | null;
  scroll_percent?: number | null;
  product_view_count?: number | null;
}

export interface EligibleCampaign {
  id: number;
  attribution_key: string | null;
  request_id: string;
  surface: string;
  title: string;
  message: string | null;
  image_url: string | null;
  cta_label: string | null;
  cta: {
    type: CampaignCtaType | string;
    product_id: number | null;
    category_id: number | null;
    promotion_id: number | null;
    internal_path: string | null;
  };
  trigger: CampaignTrigger;
  placement_hint: string;
}

export interface EligibleCampaignPayload {
  request_id: string;
  campaign: EligibleCampaign | null;
}

export interface EligibleCampaignQuery {
  placement: CampaignPlacement;
  visitor_key?: string;
  session_key?: string;
  product_id?: number;
  category_id?: number;
  cart_product_ids?: number[];
  fulfilment_method?: string;
  location_city?: string;
  location_zone?: string;
  location_available?: boolean;
  surface?: string;
}

export function fetchEligibleCampaign(
  query: EligibleCampaignQuery,
): Promise<ApiEnvelope<EligibleCampaignPayload>> {
  const params = new URLSearchParams();
  params.set('placement', query.placement);

  if (query.surface) {
    params.set('surface', query.surface);
  }

  if (query.visitor_key) {
    params.set('visitor_key', query.visitor_key);
  }

  if (query.session_key) {
    params.set('session_key', query.session_key);
  }

  if (query.product_id) {
    params.set('product_id', String(query.product_id));
  }

  if (query.category_id) {
    params.set('category_id', String(query.category_id));
  }

  if (query.fulfilment_method) {
    params.set('fulfilment_method', query.fulfilment_method);
  }

  if (query.location_city) {
    params.set('location_city', query.location_city);
  }

  if (query.location_zone) {
    params.set('location_zone', query.location_zone);
  }

  if (typeof query.location_available === 'boolean') {
    params.set('location_available', query.location_available ? '1' : '0');
  }

  for (const id of query.cart_product_ids ?? []) {
    params.append('cart_product_ids[]', String(id));
  }

  return get<ApiEnvelope<EligibleCampaignPayload>>(`/campaigns/eligible?${params.toString()}`);
}

export function recordCampaignInteraction(body: {
  campaign_id: number;
  event_type: 'impression' | 'click' | 'dismiss';
  visitor_key?: string;
  session_key?: string;
  placement?: string;
  request_id?: string;
  cta_type?: string;
}): Promise<ApiEnvelope<{ recorded: boolean }>> {
  return post<ApiEnvelope<{ recorded: boolean }>, typeof body>('/campaigns/interactions', body);
}
