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
import { reconcileStaleDiningOrderingMode } from '../utils/orderingContext';

interface ContentState {
  content: WebsiteContent | null;
  diningEnabled: boolean | null;
  hasBootstrapped: boolean;
  bootstrap: () => Promise<void>;
  reload: () => Promise<void>;
  applyDiningCapability: (diningEnabled: boolean) => void;
}

/** Stable empty fallback — never inline `?? []` in a Zustand selector (fresh [] → React #185). */
export const EMPTY_SOCIAL_LINKS: readonly WebsiteSocialLink[] = [];

let bootstrapPromise: Promise<void> | null = null;

onPublicCacheVersionChange(() => {
  void useContentStore.getState().reload();
});

function overlayDiningCapability(content: WebsiteContent, diningEnabled: boolean): WebsiteContent {
  return {
    ...content,
    fulfilment: {
      delivery_disclaimer: content.fulfilment?.delivery_disclaimer ?? null,
      ...content.fulfilment,
      dining_enabled: diningEnabled,
      dine_in_enabled: diningEnabled,
    },
  };
}

function diningCapabilityFromContent(content: WebsiteContent | null): boolean {
  return Boolean(content?.fulfilment?.dining_enabled ?? content?.fulfilment?.dine_in_enabled);
}

export const useContentStore = create<ContentState>((set, get) => ({
  content: null,
  diningEnabled: null,
  hasBootstrapped: false,
  applyDiningCapability: (diningEnabled: boolean) => {
    reconcileStaleDiningOrderingMode(diningEnabled);
    const current = get().content;

    set({
      diningEnabled,
      content: current ? overlayDiningCapability(current, diningEnabled) : current,
    });
  },
  bootstrap: async () => {
    if (get().hasBootstrapped) {
      return;
    }

    if (!bootstrapPromise) {
      bootstrapPromise = (async () => {
        const sync = await syncPublicCacheVersion();

        if (typeof sync.diningEnabled === 'boolean') {
          get().applyDiningCapability(sync.diningEnabled);
        }

        const cached = await readCachedPublicJson<Awaited<ReturnType<typeof fetchWebsiteContent>>>(
          PUBLIC_CACHE_KEYS.content,
        );

        if (cached?.data) {
          const content =
            typeof sync.diningEnabled === 'boolean'
              ? overlayDiningCapability(cached.data, sync.diningEnabled)
              : cached.data;
          set({
            content,
            diningEnabled: typeof sync.diningEnabled === 'boolean' ? sync.diningEnabled : diningCapabilityFromContent(content),
            hasBootstrapped: true,
          });
        }

        try {
          const cachedDining = diningCapabilityFromContent(get().content);
          const diningMismatch =
            typeof sync.diningEnabled === 'boolean' && sync.diningEnabled !== cachedDining;
          const response =
            sync.changed || diningMismatch
              ? await fetchWebsiteContentFromNetwork()
              : await fetchWebsiteContent({ skipNetworkIfCached: true });
          const diningEnabled =
            typeof sync.diningEnabled === 'boolean'
              ? sync.diningEnabled
              : diningCapabilityFromContent(response.data);
          const content =
            typeof sync.diningEnabled === 'boolean'
              ? overlayDiningCapability(response.data, sync.diningEnabled)
              : response.data;

          reconcileStaleDiningOrderingMode(diningEnabled);
          set({ content, diningEnabled, hasBootstrapped: true });
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
      const diningEnabled = diningCapabilityFromContent(response.data);
      reconcileStaleDiningOrderingMode(diningEnabled);
      set({ content: response.data, diningEnabled, hasBootstrapped: true });
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

/** Server dining_enabled from bootstrap overlay, then fulfilment — fail closed until either arrives. */
export function selectDiningEnabled(
  content: WebsiteContent | null,
  capability: boolean | null = null,
): boolean {
  if (capability !== null) {
    return capability;
  }

  return diningCapabilityFromContent(content);
}
