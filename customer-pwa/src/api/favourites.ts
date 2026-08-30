import { ApiEnvelope, destroy, get, post } from './client';
import { Product } from '../types/catalog';

export interface FavouriteIdsResponse {
  ids: number[];
}

export interface FavouriteListMeta {
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

export function fetchFavourites(page = 1, perPage = 20): Promise<ApiEnvelope<Product[]> & FavouriteListMeta> {
  const params = new URLSearchParams({
    page: String(page),
    per_page: String(perPage)
  });

  return get<ApiEnvelope<Product[]> & FavouriteListMeta>(`/favourites?${params.toString()}`);
}

export function fetchFavouriteIds(): Promise<ApiEnvelope<FavouriteIdsResponse>> {
  return get<ApiEnvelope<FavouriteIdsResponse>>('/favourites/ids');
}

export function addFavourite(productId: number): Promise<ApiEnvelope<Product>> {
  return post<ApiEnvelope<Product>, { product_id: number }>('/favourites', { product_id: productId });
}

export function removeFavourite(productId: number): Promise<ApiEnvelope<null>> {
  return destroy<ApiEnvelope<null>>(`/favourites/${productId}`);
}
