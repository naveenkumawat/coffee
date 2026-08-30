import { ApiEnvelope, get } from './client';
import { Product, ProductCategory, ProductListMeta, ProductVariant } from '../types/catalog';

export function fetchCategories(): Promise<ApiEnvelope<ProductCategory[]>> {
  return get<ApiEnvelope<ProductCategory[]>>('/catalog/categories');
}

export function fetchFeaturedProducts(): Promise<ApiEnvelope<Product[]>> {
  return get<ApiEnvelope<Product[]>>('/catalog/products/featured');
}

export function fetchProducts(query = ''): Promise<ApiEnvelope<Product[]> & ProductListMeta> {
  const normalizedQuery = query ? `?${query}` : '';

  return get<ApiEnvelope<Product[]> & ProductListMeta>(`/catalog/products${normalizedQuery}`);
}

export function fetchProduct(productId: string): Promise<ApiEnvelope<Product>> {
  return get<ApiEnvelope<Product>>(`/catalog/products/${productId}`);
}

export function fetchVariants(query = ''): Promise<ApiEnvelope<ProductVariant[]> & ProductListMeta> {
  const normalizedQuery = query ? `?${query}` : '';

  return get<ApiEnvelope<ProductVariant[]> & ProductListMeta>(`/catalog/variants${normalizedQuery}`);
}
