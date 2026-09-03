import { ApiEnvelope, get } from './client';
import { Product } from '../types/catalog';

export type RecommendationContext = 'home' | 'product_detail' | 'menu' | 'cart' | 'post_order';

export type RecommendationReason =
  | 'buy_again'
  | 'favourite'
  | 'because_you_viewed'
  | 'based_on_your_interests'
  | 'similar_product'
  | 'frequently_bought_together'
  | 'trending'
  | 'popular'
  | 'bestseller'
  | 'new_arrival'
  | 'featured'
  | 'complete_your_order';

export interface RecommendationItem {
  product: Product;
  reason: RecommendationReason | string;
  strategy: string;
  request_id: string;
}

export interface RecommendationPayload {
  request_id: string;
  context: RecommendationContext | string;
  cold_start: boolean;
  items: RecommendationItem[];
}

export interface RecommendationQuery {
  context: RecommendationContext;
  visitor_key?: string;
  product_id?: number;
  category_id?: number;
  cart_product_ids?: number[];
  exclude_product_ids?: number[];
  limit?: number;
}

export function fetchRecommendations(query: RecommendationQuery): Promise<ApiEnvelope<RecommendationPayload>> {
  const params = new URLSearchParams();
  params.set('context', query.context);

  if (query.visitor_key) {
    params.set('visitor_key', query.visitor_key);
  }

  if (query.product_id) {
    params.set('product_id', String(query.product_id));
  }

  if (query.category_id) {
    params.set('category_id', String(query.category_id));
  }

  if (query.limit) {
    params.set('limit', String(query.limit));
  }

  for (const id of query.cart_product_ids ?? []) {
    params.append('cart_product_ids[]', String(id));
  }

  for (const id of query.exclude_product_ids ?? []) {
    params.append('exclude_product_ids[]', String(id));
  }

  return get<ApiEnvelope<RecommendationPayload>>(`/recommendations?${params.toString()}`);
}
