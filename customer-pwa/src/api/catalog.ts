import { ApiEnvelope, get } from './client';
import { Product, ProductCategory, ProductFlavour, ProductListMeta, ProductVariant } from '../types/catalog';

export interface ProductQueryFilters {
  search?: string | null;
  categoryId?: number | null;
  categoryIds?: number[];
  flavourId?: number | null;
  flavourIds?: number[];
  featured?: boolean;
  isNew?: boolean;
  isBestseller?: boolean;
  perPage?: number;
}

export function fetchCategories(): Promise<ApiEnvelope<ProductCategory[]>> {
  return get<ApiEnvelope<ProductCategory[]>>('/catalog/categories');
}

export function fetchFlavours(): Promise<ApiEnvelope<ProductFlavour[]>> {
  return get<ApiEnvelope<ProductFlavour[]>>('/catalog/flavours');
}

export function fetchFeaturedProducts(): Promise<ApiEnvelope<Product[]>> {
  return get<ApiEnvelope<Product[]>>('/catalog/products/featured');
}

export function buildProductQuery(filters: ProductQueryFilters = {}): string {
  const params = new URLSearchParams();

  if (filters.search?.trim()) {
    params.set('search', filters.search.trim());
  }

  const categoryIds = [
    ...(filters.categoryIds ?? []),
    ...(filters.categoryId ? [filters.categoryId] : []),
  ].filter((id, index, list) => id > 0 && list.indexOf(id) === index);

  const flavourIds = [
    ...(filters.flavourIds ?? []),
    ...(filters.flavourId ? [filters.flavourId] : []),
  ].filter((id, index, list) => id > 0 && list.indexOf(id) === index);

  for (const categoryId of categoryIds) {
    params.append('product_category_ids[]', String(categoryId));
  }

  for (const flavourId of flavourIds) {
    params.append('product_flavour_ids[]', String(flavourId));
  }

  if (filters.featured) {
    params.set('featured', 'featured');
  }

  if (filters.isNew) {
    params.set('new', 'new');
  }

  if (filters.isBestseller) {
    params.set('bestseller', 'bestseller');
  }

  if (filters.perPage) {
    params.set('per_page', String(filters.perPage));
  }

  return params.toString();
}

export function fetchNewProducts(perPage = 8): Promise<ApiEnvelope<Product[]> & ProductListMeta> {
  return fetchProducts(buildProductQuery({ isNew: true, perPage }));
}

export function fetchBestsellerProducts(perPage = 8): Promise<ApiEnvelope<Product[]> & ProductListMeta> {
  return fetchProducts(buildProductQuery({ isBestseller: true, perPage }));
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
