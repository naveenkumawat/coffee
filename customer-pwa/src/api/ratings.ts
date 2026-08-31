import { ApiEnvelope, destroy, get, post, put } from './client';
import {
  MyProductRating,
  ProductRatingMutationPayload,
  ProductRatingsPayload,
} from '../types/rating';

export function fetchProductRatings(
  productId: number | string,
  page = 1,
  perPage = 10,
): Promise<ApiEnvelope<ProductRatingsPayload>> {
  const params = new URLSearchParams({
    page: String(page),
    per_page: String(perPage),
  });

  return get<ApiEnvelope<ProductRatingsPayload>>(
    `/catalog/products/${productId}/ratings?${params.toString()}`,
  );
}

export function submitProductRating(
  productId: number,
  payload: { rating: number; review?: string | null },
): Promise<ApiEnvelope<ProductRatingMutationPayload>> {
  return post<ApiEnvelope<ProductRatingMutationPayload>, typeof payload>(
    `/products/${productId}/rating`,
    payload,
  );
}

export function updateProductRating(
  productId: number,
  payload: { rating: number; review?: string | null },
): Promise<ApiEnvelope<ProductRatingMutationPayload>> {
  return put<ApiEnvelope<ProductRatingMutationPayload>, typeof payload>(
    `/products/${productId}/rating`,
    payload,
  );
}

export function deleteProductRating(
  productId: number,
): Promise<ApiEnvelope<ProductRatingMutationPayload>> {
  return destroy<ApiEnvelope<ProductRatingMutationPayload>>(`/products/${productId}/rating`);
}

export type { MyProductRating };
