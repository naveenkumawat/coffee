import { create } from 'zustand';
import { fetchWebsiteContent, fetchWebsiteContentFromNetwork } from '../api/content';
import { readCachedPublicJson } from '../cache/publicCache';
import { PUBLIC_CACHE_KEYS } from '../cache/keys';
import { onPublicCacheVersionChange, syncPublicCacheVersion } from '../cache/version';
import {
  DEFAULT_BRAND_NAME,
  DEFAULT_HOME_SLOGAN,
  WebsiteAvailabilityContent,
  WebsiteContent,
  WebsiteSocialLink,
} from '../types/content';

interface ContentState {
  content: WebsiteContent | null;
  hasBootstrapped: boolean;
  bootstrap: () => Promise<void>;
  reload: () => Promise<void>;
}

/** Stable empty fallback — never inline `?? []` in a Zustand selector (fresh [] → React #185). */
export const EMPTY_SOCIAL_LINKS: readonly WebsiteSocialLink[] = [];

let bootstrapPromise: Promise<void> | null = null;

onPublicCacheVersionChange(() => {
  void useContentStore.getState().reload();
});

export const useContentStore = create<ContentState>((set, get) => ({
  content: null,
  hasBootstrapped: false,
  bootstrap: async () => {
    if (get().hasBootstrapped) {
      return;
    }

    if (!bootstrapPromise) {
      bootstrapPromise = (async () => {
        const cached = await readCachedPublicJson<Awaited<ReturnType<typeof fetchWebsiteContent>>>(
          PUBLIC_CACHE_KEYS.content,
        );

        if (cached?.data) {
          set({ content: cached.data, hasBootstrapped: true });
        }

        await syncPublicCacheVersion();

        try {
          const response = await fetchWebsiteContent({ skipNetworkIfCached: true });
          set({ content: response.data, hasBootstrapped: true });
        } catch {
          set({ content: get().content, hasBootstrapped: true });
        }
      })().finally(() => {
        bootstrapPromise = null;
      });
    }

    await bootstrapPromise;
  },
  reload: async () => {
    try {
      const response = await fetchWebsiteContentFromNetwork();
      set({ content: response.data, hasBootstrapped: true });
    } catch {
      // Keep last known public content when offline.
    }
  },
}));

export function selectBrandName(content: WebsiteContent | null): string {
  return content?.branding?.name?.trim() || content?.business?.name?.trim() || DEFAULT_BRAND_NAME;
}

export function selectHomeSlogan(content: WebsiteContent | null): string {
  return content?.branding?.tagline?.trim() || content?.hero?.subtitle?.trim() || DEFAULT_HOME_SLOGAN;
}

export function selectBrandLogoUrl(content: WebsiteContent | null): string | null {
  const url = content?.branding?.logo_url?.trim();

  return url || null;
}

export function selectBrandDisplayMode(
  content: WebsiteContent | null,
): 'logo' | 'logo_name' | 'logo_name_tagline' {
  const mode = content?.branding?.display_mode;

  if (mode === 'logo' || mode === 'logo_name' || mode === 'logo_name_tagline') {
    return mode;
  }

  return 'logo_name_tagline';
}

export function selectSocialLinks(content: WebsiteContent | null): readonly WebsiteSocialLink[] {
  return content?.social_links ?? EMPTY_SOCIAL_LINKS;
}

export function selectAvailability(content: WebsiteContent | null): WebsiteAvailabilityContent | null {
  return content?.availability ?? null;
}

/** Server fulfilment.dining_enabled — fail closed until public content arrives. */
export function selectDiningEnabled(content: WebsiteContent | null): boolean {
  return Boolean(content?.fulfilment?.dining_enabled ?? content?.fulfilment?.dine_in_enabled);
}
