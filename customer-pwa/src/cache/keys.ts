export const PUBLIC_CACHE_DB = 'sip-the-soul';
export const PUBLIC_CACHE_STORE = 'public-content';
export const PUBLIC_CACHE_VERSION_KEY = 'sip-the-soul.cache-version';
export const MEDIA_CACHE_PREFIX = 'sip-the-soul-media-';

export const PUBLIC_CACHE_KEYS = {
  content: 'content',
  catalogProducts: 'catalog:products',
  catalogCategories: 'catalog:categories',
  catalogFlavours: 'catalog:flavours',
  catalogEtag: 'catalog:products:etag',
} as const;

const OBSOLETE_STORAGE_KEYS = [
  'coffee_public_cache_version',
  'the88coffees.cache-version',
  'The88Coffees.cache-version',
];

export function readStoredCacheVersion(): string | null {
  try {
    const value = window.localStorage.getItem(PUBLIC_CACHE_VERSION_KEY)?.trim();

    return value || null;
  } catch {
    return null;
  }
}

export function writeStoredCacheVersion(version: string): void {
  try {
    window.localStorage.setItem(PUBLIC_CACHE_VERSION_KEY, version);
  } catch {
    // Quota / private mode — caching is optional.
  }
}

export function clearObsoleteCacheKeys(): void {
  try {
    for (const key of OBSOLETE_STORAGE_KEYS) {
      window.localStorage.removeItem(key);
    }
  } catch {
    // ignore
  }
}
