import { create } from 'zustand';
import { fetchWebsiteContent } from '../api/content';
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
}

/** Stable empty fallback — never inline `?? []` in a Zustand selector (fresh [] → React #185). */
export const EMPTY_SOCIAL_LINKS: readonly WebsiteSocialLink[] = [];

let bootstrapPromise: Promise<void> | null = null;

export const useContentStore = create<ContentState>((set, get) => ({
  content: null,
  hasBootstrapped: false,
  bootstrap: async () => {
    if (get().hasBootstrapped) {
      return;
    }

    if (!bootstrapPromise) {
      bootstrapPromise = fetchWebsiteContent()
        .then((response) => {
          set({ content: response.data, hasBootstrapped: true });
        })
        .catch(() => {
          set({ content: null, hasBootstrapped: true });
        })
        .finally(() => {
          bootstrapPromise = null;
        });
    }

    await bootstrapPromise;
  },
}));

export function selectBrandName(content: WebsiteContent | null): string {
  return content?.business?.name?.trim() || DEFAULT_BRAND_NAME;
}

export function selectHomeSlogan(content: WebsiteContent | null): string {
  return content?.hero?.subtitle?.trim() || DEFAULT_HOME_SLOGAN;
}

export function selectSocialLinks(content: WebsiteContent | null): readonly WebsiteSocialLink[] {
  return content?.social_links ?? EMPTY_SOCIAL_LINKS;
}

export function selectAvailability(content: WebsiteContent | null): WebsiteAvailabilityContent | null {
  return content?.availability ?? null;
}
