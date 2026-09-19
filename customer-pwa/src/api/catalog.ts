import { ApiEnvelope, get, getConditional } from './client';
import { readCachedPublicJson, writeCachedPublicJson } from '../cache/publicCache';
import { PUBLIC_CACHE_KEYS } from '../cache/keys';
import { onPublicCacheVersionChange } from '../cache/version';
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

let menuCatalogueCache: Product[] | null = null;
let menuCatalogueEtag: string | null = null;

onPublicCacheVersionChange(() => {
  clearMenuCatalogueCache();
});

export async function fetchCategories(): Promise<ApiEnvelope<ProductCategory[]>> {
  const cached = await readCachedPublicJson<ApiEnvelope<ProductCategory[]>>(PUBLIC_CACHE_KEYS.catalogCategories);

  if (cached) {
    return cached;
  }

  const fresh = await get<ApiEnvelope<ProductCategory[]>>('/catalog/categories');
  await writeCachedPublicJson(PUBLIC_CACHE_KEYS.catalogCategories, fresh);

  return fresh;
}

export async function fetchFlavours(): Promise<ApiEnvelope<ProductFlavour[]>> {
  const cached = await readCachedPublicJson<ApiEnvelope<ProductFlavour[]>>(PUBLIC_CACHE_KEYS.catalogFlavours);

  if (cached) {
    return cached;
  }

  const fresh = await get<ApiEnvelope<ProductFlavour[]>>('/catalog/flavours');
  await writeCachedPublicJson(PUBLIC_CACHE_KEYS.catalogFlavours, fresh);

  return fresh;
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

/**
 * Full public menu catalogue with in-memory + ETag revalidation.
 * Does not cache authenticated/customer-specific fields.
 */
export async function fetchMenuCatalogue(force = false): Promise<Product[]> {
  if (force) {
    menuCatalogueCache = null;
    menuCatalogueEtag = null;
  }

  const cachedRecord = await readCachedPublicJson<{ products: Product[]; etag: string | null }>(
    PUBLIC_CACHE_KEYS.catalogProducts,
  );

  if (!force && cachedRecord?.products?.length) {
    menuCatalogueCache = cachedRecord.products;
    menuCatalogueEtag = cachedRecord.etag ?? menuCatalogueEtag;
  }

  const revalidate = async (): Promise<Product[]> => {
    const result = await getConditional<ApiEnvelope<Product[]>>(
      '/catalog/products',
      force ? null : menuCatalogueEtag,
    );

    if (result.notModified && menuCatalogueCache) {
      return menuCatalogueCache;
    }

    const products = result.data?.data ?? menuCatalogueCache ?? [];
    menuCatalogueCache = products;
    menuCatalogueEtag = result.etag;
    await writeCachedPublicJson(PUBLIC_CACHE_KEYS.catalogProducts, {
      products,
      etag: result.etag,
    });

    return products;
  };

  if (!force && menuCatalogueCache) {
    void revalidate().catch(() => undefined);

    return menuCatalogueCache;
  }

  try {
    return await revalidate();
  } catch (error) {
    if (menuCatalogueCache) {
      return menuCatalogueCache;
    }

    throw error;
  }
}

export function clearMenuCatalogueCache(): void {
  menuCatalogueCache = null;
  menuCatalogueEtag = null;
}

export function fetchProduct(productId: string): Promise<ApiEnvelope<Product>> {
  return get<ApiEnvelope<Product>>(`/catalog/products/${productId}`);
}

export function fetchVariants(query = ''): Promise<ApiEnvelope<ProductVariant[]> & ProductListMeta> {
  const normalizedQuery = query ? `?${query}` : '';

  return get<ApiEnvelope<ProductVariant[]> & ProductListMeta>(`/catalog/variants${normalizedQuery}`);
}
