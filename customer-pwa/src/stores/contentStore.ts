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
import { cartCapabilityFromContent, resolveCartEnabled } from '../utils/cartCapability';
import { diningCapabilityFromContent, resolveDiningCapability } from '../utils/diningCapability';
import { reconcileStaleDiningOrderingMode } from '../utils/orderingContext';

interface ContentState {
  content: WebsiteContent | null;
  diningEnabled: boolean | null;
  cartEnabled: boolean | null;
  hasBootstrapped: boolean;
  bootstrap: () => Promise<void>;
  reload: () => Promise<void>;
  applyDiningCapability: (diningEnabled: boolean) => void;
  applyCartCapability: (cartEnabled: boolean) => void;
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

function overlayCartCapability(content: WebsiteContent, cartEnabled: boolean): WebsiteContent {
  return {
    ...content,
    fulfilment: {
      delivery_disclaimer: content.fulfilment?.delivery_disclaimer ?? null,
      ...content.fulfilment,
      cart_enabled: cartEnabled,
    },
  };
}

function withPublicCapabilities(
  content: WebsiteContent,
  diningEnabled: boolean,
  cartEnabled: boolean | null,
): WebsiteContent {
  const withDining = overlayDiningCapability(content, diningEnabled);

  return typeof cartEnabled === 'boolean' ? overlayCartCapability(withDining, cartEnabled) : withDining;
}

function settledDiningEnabled(input: {
  bootstrapDiningEnabled: boolean | null;
  storedDiningEnabled: boolean | null;
  content: WebsiteContent | null;
}): boolean {
  return (
    resolveDiningCapability(input) ?? false
  );
}

function settledCartEnabled(input: {
  bootstrapCartEnabled: boolean | null;
  storedCartEnabled: boolean | null;
  content: WebsiteContent | null;
}): boolean {
  return resolveCartEnabled(input);
}

export const useContentStore = create<ContentState>((set, get) => ({
  content: null,
  diningEnabled: null,
  cartEnabled: null,
  hasBootstrapped: false,
  applyDiningCapability: (diningEnabled: boolean) => {
    reconcileStaleDiningOrderingMode(diningEnabled);
    const current = get().content;

    set({
      diningEnabled,
      content: current ? withPublicCapabilities(current, diningEnabled, get().cartEnabled) : current,
    });
  },
  applyCartCapability: (cartEnabled: boolean) => {
    const current = get().content;

    set({
      cartEnabled,
      content: current ? overlayCartCapability(current, cartEnabled) : current,
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

        if (typeof sync.cartEnabled === 'boolean') {
          get().applyCartCapability(sync.cartEnabled);
        }

        const cached = await readCachedPublicJson<Awaited<ReturnType<typeof fetchWebsiteContent>>>(
          PUBLIC_CACHE_KEYS.content,
        );

        if (cached?.data) {
          const diningEnabled = settledDiningEnabled({
            bootstrapDiningEnabled: typeof sync.diningEnabled === 'boolean' ? sync.diningEnabled : null,
            storedDiningEnabled: get().diningEnabled,
            content: cached.data,
          });
          const cartEnabled = settledCartEnabled({
            bootstrapCartEnabled: typeof sync.cartEnabled === 'boolean' ? sync.cartEnabled : null,
            storedCartEnabled: get().cartEnabled,
            content: cached.data,
          });
          set({
            content: withPublicCapabilities(cached.data, diningEnabled, cartEnabled),
            diningEnabled,
            cartEnabled,
            hasBootstrapped: true,
          });
        }

        try {
          const cachedDining = diningCapabilityFromContent(get().content);
          const diningMismatch =
            typeof sync.diningEnabled === 'boolean' && sync.diningEnabled !== cachedDining;
          const cachedCart = cartCapabilityFromContent(get().content);
          const cartMismatch =
            typeof sync.cartEnabled === 'boolean' &&
            (cachedCart === null || cachedCart !== sync.cartEnabled);
          const response =
            sync.changed || diningMismatch || cartMismatch
              ? await fetchWebsiteContentFromNetwork()
              : await fetchWebsiteContent({ skipNetworkIfCached: true });
          const diningEnabled = settledDiningEnabled({
            bootstrapDiningEnabled: typeof sync.diningEnabled === 'boolean' ? sync.diningEnabled : null,
            storedDiningEnabled: get().diningEnabled,
            content: response.data,
          });
          const cartEnabled = settledCartEnabled({
            bootstrapCartEnabled: typeof sync.cartEnabled === 'boolean' ? sync.cartEnabled : null,
            storedCartEnabled: get().cartEnabled,
            content: response.data,
          });
          const content = withPublicCapabilities(response.data, diningEnabled, cartEnabled);

          reconcileStaleDiningOrderingMode(diningEnabled);
          set({ content, diningEnabled, cartEnabled, hasBootstrapped: true });
        } catch {
          const diningEnabled = settledDiningEnabled({
            bootstrapDiningEnabled: typeof sync.diningEnabled === 'boolean' ? sync.diningEnabled : null,
            storedDiningEnabled: get().diningEnabled,
            content: get().content,
          });
          const cartEnabled = settledCartEnabled({
            bootstrapCartEnabled: typeof sync.cartEnabled === 'boolean' ? sync.cartEnabled : null,
            storedCartEnabled: get().cartEnabled,
            content: get().content,
          });
          const current = get().content;

          reconcileStaleDiningOrderingMode(diningEnabled);
          set({
            content: current ? withPublicCapabilities(current, diningEnabled, cartEnabled) : current,
            diningEnabled,
            cartEnabled,
            hasBootstrapped: true,
          });
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
      const cartFromContent = cartCapabilityFromContent(response.data);
      const cartEnabled = cartFromContent ?? get().cartEnabled ?? true;
      reconcileStaleDiningOrderingMode(diningEnabled);
      set({
        content: withPublicCapabilities(response.data, diningEnabled, cartEnabled),
        diningEnabled,
        cartEnabled,
        hasBootstrapped: true,
      });
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

/** Server cart_enabled from bootstrap overlay, then fulfilment. Unknown stays enabled. */
export function selectCartEnabled(
  content: WebsiteContent | null,
  capability: boolean | null = null,
): boolean {
  return resolveCartEnabled({
    bootstrapCartEnabled: capability,
    storedCartEnabled: null,
    content,
  });
}

/** Server dining_enabled from bootstrap overlay, then fulfilment — fail closed until either arrives. */
export function selectDiningEnabled(
  content: WebsiteContent | null,
  capability: boolean | null = null,
): boolean {
  return (
    resolveDiningCapability({
      bootstrapDiningEnabled: capability,
      storedDiningEnabled: null,
      content,
    }) ?? false
  );
}
