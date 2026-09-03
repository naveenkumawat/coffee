import { BehaviourEventType, ingestBehaviourEvent, mergeBehaviourVisitor } from '../api/behaviour';
import { useContentStore } from '../stores/contentStore';
import { getOrCreateVisitorId, rotateVisitorId } from '../utils/visitorId';

const recentKeys = new Map<string, number>();
const DEDUPE_WINDOW_MS = 2500;

function trackingEnabled(): boolean {
  const content = useContentStore.getState().content;

  if (content?.behaviour && typeof content.behaviour.tracking_enabled === 'boolean') {
    return content.behaviour.tracking_enabled;
  }

  return true;
}

function pageContext(): string {
  try {
    return `${window.location.pathname}${window.location.search}`.slice(0, 160);
  } catch {
    return '/';
  }
}

function shouldDedupe(key: string): boolean {
  const now = Date.now();
  const previous = recentKeys.get(key);

  if (previous !== undefined && now - previous < DEDUPE_WINDOW_MS) {
    return true;
  }

  recentKeys.set(key, now);

  if (recentKeys.size > 200) {
    const cutoff = now - DEDUPE_WINDOW_MS * 4;

    for (const [entryKey, stampedAt] of recentKeys) {
      if (stampedAt < cutoff) {
        recentKeys.delete(entryKey);
      }
    }
  }

  return false;
}

export type TrackInput = {
  event_type: BehaviourEventType;
  product_id?: number;
  product_category_id?: number;
  product_variant_id?: number;
  metadata?: Record<string, unknown>;
  idempotency_key?: string;
  dedupe_key?: string;
};

/**
 * Fire-and-forget behavioural tracking. Never throws to callers; never blocks UX.
 */
export function trackBehaviour(input: TrackInput): void {
  if (!trackingEnabled()) {
    return;
  }

  if (typeof navigator !== 'undefined' && navigator.onLine === false) {
    return;
  }

  const dedupeKey = input.dedupe_key
    ?? `${input.event_type}:${input.product_id ?? ''}:${input.product_category_id ?? ''}:${input.product_variant_id ?? ''}:${JSON.stringify(input.metadata ?? {})}`;

  if (shouldDedupe(dedupeKey)) {
    return;
  }

  const visitorKey = getOrCreateVisitorId();

  void ingestBehaviourEvent({
    event_type: input.event_type,
    visitor_key: visitorKey,
    product_id: input.product_id,
    product_category_id: input.product_category_id,
    product_variant_id: input.product_variant_id,
    page_context: pageContext(),
    metadata: input.metadata,
    occurred_at: new Date().toISOString(),
    idempotency_key: input.idempotency_key,
  }).catch(() => {
    // Tracking must never surface errors in the customer journey.
  });
}

/**
 * Associate anonymous guest events with the authenticated customer (idempotent).
 * Rotates visitor id if the key was claimed by another customer (shared device).
 */
export async function associateVisitorWithCustomer(): Promise<void> {
  if (!trackingEnabled()) {
    return;
  }

  const visitorKey = getOrCreateVisitorId();

  try {
    const response = await mergeBehaviourVisitor(visitorKey);

    if (response.data.merged === false && response.data.reason === 'visitor_claimed') {
      rotateVisitorId();
    }
  } catch {
    // Non-blocking
  }
}
