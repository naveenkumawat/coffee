import { getKnownPublicCacheVersion } from './version';
import { readPublicRecord, writePublicRecord } from './idb';

export async function readCachedPublicJson<T>(key: string): Promise<T | null> {
  const record = await readPublicRecord<T>(key);

  if (!record) {
    return null;
  }

  const version = getKnownPublicCacheVersion();

  if (version && record.version !== version) {
    return null;
  }

  return record.payload;
}

export async function writeCachedPublicJson<T>(key: string, payload: T): Promise<void> {
  const version = getKnownPublicCacheVersion() ?? 'pending';

  try {
    await writePublicRecord(key, payload, version);
  } catch {
    // Quota / private browsing — continue from network.
  }
}

export async function staleWhileRevalidatePublic<T>(
  key: string,
  fetcher: () => Promise<T>,
): Promise<T> {
  const cached = await readCachedPublicJson<T>(key);

  if (cached !== null) {
    void fetcher()
      .then((fresh) => writeCachedPublicJson(key, fresh))
      .catch(() => undefined);

    return cached;
  }

  const fresh = await fetcher();
  await writeCachedPublicJson(key, fresh);

  return fresh;
}
