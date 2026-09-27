import type { WebsiteContent } from '../types/content';

export function cartCapabilityFromContent(content: WebsiteContent | null): boolean | null {
  const value = content?.fulfilment?.cart_enabled;

  return typeof value === 'boolean' ? value : null;
}

/**
 * 1. app-bootstrap cart_enabled
 * 2. stored capability from a prior successful sync
 * 3. cached /content fulfilment flag
 * 4. true when unknown, so older clients keep retail ordering
 *
 * Bootstrap always wins over stale IndexedDB content.
 */
export function resolveCartEnabled(input: {
  bootstrapCartEnabled: boolean | null;
  storedCartEnabled: boolean | null;
  content: WebsiteContent | null;
}): boolean {
  if (typeof input.bootstrapCartEnabled === 'boolean') {
    return input.bootstrapCartEnabled;
  }

  if (input.storedCartEnabled !== null) {
    return input.storedCartEnabled;
  }

  const fromContent = cartCapabilityFromContent(input.content);

  if (fromContent !== null) {
    return fromContent;
  }

  return true;
}
