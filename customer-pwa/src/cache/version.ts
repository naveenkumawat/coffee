import { get, ApiEnvelope } from '../api/client';
import { clearPublicStore } from './idb';
import {
  clearObsoleteCacheKeys,
  PUBLIC_CACHE_VERSION_KEY,
  readStoredCacheVersion,
  writeStoredCacheVersion,
} from './keys';

export interface AppBootstrapPayload {
  cache_version: string;
  catalog_version?: string;
  content_version?: string;
  media_version?: string;
}

let lastKnownVersion: string | null = readStoredCacheVersion();
let syncPromise: Promise<string | null> | null = null;
const listeners = new Set<(version: string) => void>();

export function getKnownPublicCacheVersion(): string | null {
  return lastKnownVersion ?? readStoredCacheVersion();
}

export function onPublicCacheVersionChange(listener: (version: string) => void): () => void {
  listeners.add(listener);

  return () => {
    listeners.delete(listener);
  };
}

function notify(version: string): void {
  for (const listener of listeners) {
    listener(version);
  }
}

export async function purgePublicClientCaches(): Promise<void> {
  await clearPublicStore();
  await purgePublicMediaCache();
}

async function purgePublicMediaCache(): Promise<void> {
  try {
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.controller?.postMessage({ type: 'PURGE_PUBLIC_MEDIA' });
    }

    if (typeof caches === 'undefined') {
      return;
    }

    const keys = await caches.keys();
    await Promise.all(
      keys
        .filter((key) => key.startsWith('sip-the-soul-media-'))
        .map((key) => caches.delete(key)),
    );
  } catch {
    // Cache Storage may be unavailable.
  }
}

export async function applyServerCacheVersion(serverVersion: string): Promise<boolean> {
  const previous = getKnownPublicCacheVersion();

  if (previous === serverVersion) {
    lastKnownVersion = serverVersion;
    writeStoredCacheVersion(serverVersion);

    return false;
  }

  await purgePublicClientCaches();
  lastKnownVersion = serverVersion;
  writeStoredCacheVersion(serverVersion);
  notify(serverVersion);

  return true;
}

export async function syncPublicCacheVersion(force = false): Promise<string | null> {
  clearObsoleteCacheKeys();

  if (syncPromise && !force) {
    return syncPromise;
  }

  syncPromise = (async () => {
    try {
      const response = await get<ApiEnvelope<AppBootstrapPayload>>('/app-bootstrap');
      const version = response.data?.cache_version?.trim();

      if (!version) {
        return getKnownPublicCacheVersion();
      }

      await applyServerCacheVersion(version);

      return version;
    } catch {
      return getKnownPublicCacheVersion();
    } finally {
      syncPromise = null;
    }
  })();

  return syncPromise;
}

export function localStorageHoldsPublicCacheJson(): boolean {
  try {
    const raw = window.localStorage.getItem(PUBLIC_CACHE_VERSION_KEY);

    return Boolean(raw && raw.trim().startsWith('{'));
  } catch {
    return false;
  }
}
