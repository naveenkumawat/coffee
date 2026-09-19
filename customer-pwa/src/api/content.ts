import { ApiEnvelope, get } from './client';
import { PUBLIC_CACHE_KEYS } from '../cache/keys';
import { readCachedPublicJson, staleWhileRevalidatePublic, writeCachedPublicJson } from '../cache/publicCache';
import { WebsiteContent } from '../types/content';

export async function fetchWebsiteContent(options: { skipNetworkIfCached?: boolean } = {}): Promise<ApiEnvelope<WebsiteContent>> {
  if (options.skipNetworkIfCached) {
    const cached = await readCachedPublicJson<ApiEnvelope<WebsiteContent>>(PUBLIC_CACHE_KEYS.content);

    if (cached) {
      return cached;
    }
  }

  return staleWhileRevalidatePublic(PUBLIC_CACHE_KEYS.content, () => get<ApiEnvelope<WebsiteContent>>('/content'));
}

export async function fetchWebsiteContentFromNetwork(): Promise<ApiEnvelope<WebsiteContent>> {
  const fresh = await get<ApiEnvelope<WebsiteContent>>('/content');
  await writeCachedPublicJson(PUBLIC_CACHE_KEYS.content, fresh);

  return fresh;
}
