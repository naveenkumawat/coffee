import { PUBLIC_CACHE_DB, PUBLIC_CACHE_STORE } from './keys';

export interface PublicCacheRecord<T> {
  key: string;
  payload: T;
  version: string;
  cachedAt: number;
}

function openDb(): Promise<IDBDatabase | null> {
  if (typeof indexedDB === 'undefined') {
    return Promise.resolve(null);
  }

  return new Promise((resolve) => {
    try {
      const request = indexedDB.open(PUBLIC_CACHE_DB, 1);

      request.onupgradeneeded = () => {
        const db = request.result;

        if (!db.objectStoreNames.contains(PUBLIC_CACHE_STORE)) {
          db.createObjectStore(PUBLIC_CACHE_STORE, { keyPath: 'key' });
        }
      };

      request.onsuccess = () => resolve(request.result);
      request.onerror = () => resolve(null);
    } catch {
      resolve(null);
    }
  });
}

export async function readPublicRecord<T>(key: string): Promise<PublicCacheRecord<T> | null> {
  const db = await openDb();

  if (!db) {
    return null;
  }

  return new Promise((resolve) => {
    try {
      const tx = db.transaction(PUBLIC_CACHE_STORE, 'readonly');
      const request = tx.objectStore(PUBLIC_CACHE_STORE).get(key);

      request.onsuccess = () => {
        resolve((request.result as PublicCacheRecord<T> | undefined) ?? null);
      };
      request.onerror = () => resolve(null);
    } catch {
      resolve(null);
    }
  });
}

export async function writePublicRecord<T>(key: string, payload: T, version: string): Promise<void> {
  const db = await openDb();

  if (!db) {
    return;
  }

  const record: PublicCacheRecord<T> = {
    key,
    payload: JSON.parse(JSON.stringify(payload)) as T,
    version,
    cachedAt: Date.now(),
  };

  await new Promise<void>((resolve) => {
    try {
      const tx = db.transaction(PUBLIC_CACHE_STORE, 'readwrite');
      tx.objectStore(PUBLIC_CACHE_STORE).put(record);
      tx.oncomplete = () => resolve();
      tx.onerror = () => resolve();
      tx.onabort = () => resolve();
    } catch {
      resolve();
    }
  });
}

export async function clearPublicStore(): Promise<void> {
  const db = await openDb();

  if (!db) {
    return;
  }

  await new Promise<void>((resolve) => {
    try {
      const tx = db.transaction(PUBLIC_CACHE_STORE, 'readwrite');
      tx.objectStore(PUBLIC_CACHE_STORE).clear();
      tx.oncomplete = () => resolve();
      tx.onerror = () => resolve();
    } catch {
      resolve();
    }
  });
}
