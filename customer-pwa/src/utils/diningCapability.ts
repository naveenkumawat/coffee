import type { WebsiteContent } from '../types/content';

export function diningCapabilityFromContent(content: WebsiteContent | null): boolean {
  return Boolean(content?.fulfilment?.dining_enabled ?? content?.fulfilment?.dine_in_enabled);
}

function contentDeclaresDiningCapability(content: WebsiteContent | null): boolean {
  const fulfilment = content?.fulfilment;

  return (
    Boolean(fulfilment) &&
    (typeof fulfilment?.dining_enabled === 'boolean' || typeof fulfilment?.dine_in_enabled === 'boolean')
  );
}

/**
 * 1. app-bootstrap dining_enabled
 * 2. stored capability (including overlay from a prior successful sync)
 * 3. cached /content fulfilment flag
 * 4. null = unknown (callers fail closed for *new* Dining)
 *
 * Bootstrap always wins over stale IndexedDB content.
 */
export function resolveDiningCapability(input: {
  bootstrapDiningEnabled: boolean | null;
  storedDiningEnabled: boolean | null;
  content: WebsiteContent | null;
}): boolean | null {
  if (typeof input.bootstrapDiningEnabled === 'boolean') {
    return input.bootstrapDiningEnabled;
  }

  if (input.storedDiningEnabled !== null) {
    return input.storedDiningEnabled;
  }

  if (contentDeclaresDiningCapability(input.content)) {
    return diningCapabilityFromContent(input.content);
  }

  return null;
}

/** Fail closed for starting new Dining when capability is still unknown. */
export function diningEnabledForNewSession(capability: boolean | null): boolean {
  return capability === true;
}
