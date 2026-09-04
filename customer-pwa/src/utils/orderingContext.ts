/**
 * Explicit customer ordering context.
 * Dining mode routes customized products into the dining round draft (never the retail cart).
 */
export type OrderingContext =
  | { type: 'retail' }
  | {
      type: 'dining';
      diningSessionId: string;
      tableLabel?: string;
      /** Quantity of items in the current dining next-round draft (footer Cart badge). */
      draftItemCount?: number;
    };

const STORAGE_KEY = 'coffee.ordering_context.v1';
const CHANGE_EVENT = 'coffee:ordering-context';

/** Stable retail snapshot — required for useSyncExternalStore getSnapshot caching. */
export const RETAIL_ORDERING_CONTEXT: OrderingContext = Object.freeze({ type: 'retail' });

let cachedRaw: string | null | undefined = undefined;
let cachedContext: OrderingContext = RETAIL_ORDERING_CONTEXT;

function isNonEmptyString(value: unknown): value is string {
  return typeof value === 'string' && value.trim().length > 0;
}

function normalizeDraftItemCount(value: unknown): number | undefined {
  if (typeof value === 'number' && Number.isFinite(value) && value >= 0) {
    return Math.floor(value);
  }

  if (typeof value === 'string' && value.trim() !== '') {
    const parsed = Number(value);
    if (Number.isFinite(parsed) && parsed >= 0) {
      return Math.floor(parsed);
    }
  }

  return undefined;
}

export function diningDraftItemCount(
  drafts: Array<{ quantity?: number | null }> | null | undefined,
): number {
  if (!drafts?.length) {
    return 0;
  }

  return drafts.reduce((sum, draft) => sum + Math.max(0, Number(draft.quantity ?? 0)), 0);
}

/**
 * Normalize persisted / in-memory shapes. Invalid or incomplete dining state
 * becomes retail (callers may clear storage separately).
 */
export function normalizeOrderingContext(value: unknown): OrderingContext {
  if (!value || typeof value !== 'object') {
    return RETAIL_ORDERING_CONTEXT;
  }

  const record = value as Record<string, unknown>;

  if (record.type === 'retail') {
    return RETAIL_ORDERING_CONTEXT;
  }

  if (record.type !== 'dining') {
    return RETAIL_ORDERING_CONTEXT;
  }

  const rawId = record.diningSessionId ?? record.sessionId ?? record.dining_session_id;
  const diningSessionId =
    typeof rawId === 'number' && Number.isFinite(rawId)
      ? String(rawId)
      : isNonEmptyString(rawId)
        ? String(rawId).trim()
        : '';

  if (!diningSessionId) {
    return RETAIL_ORDERING_CONTEXT;
  }

  const rawLabel = record.tableLabel ?? record.table_label ?? record.table;
  const tableLabel =
    typeof rawLabel === 'string' && rawLabel.trim()
      ? rawLabel.trim()
      : typeof rawLabel === 'object' &&
          rawLabel !== null &&
          isNonEmptyString((rawLabel as { label?: unknown }).label)
        ? String((rawLabel as { label: string }).label).trim()
        : undefined;

  const draftItemCount = normalizeDraftItemCount(
    record.draftItemCount ?? record.draft_item_count ?? record.draftCount,
  );

  return {
    type: 'dining',
    diningSessionId,
    ...(tableLabel ? { tableLabel } : {}),
    ...(draftItemCount !== undefined ? { draftItemCount } : {}),
  };
}

function contextsEqual(a: OrderingContext, b: OrderingContext): boolean {
  if (a.type !== b.type) {
    return false;
  }

  if (a.type === 'retail' || b.type === 'retail') {
    return true;
  }

  return (
    a.diningSessionId === b.diningSessionId &&
    a.tableLabel === b.tableLabel &&
    (a.draftItemCount ?? 0) === (b.draftItemCount ?? 0)
  );
}

function rememberSnapshot(raw: string | null, context: OrderingContext): OrderingContext {
  if (cachedRaw === raw && contextsEqual(cachedContext, context)) {
    return cachedContext;
  }

  cachedRaw = raw;
  cachedContext =
    context.type === 'retail'
      ? RETAIL_ORDERING_CONTEXT
      : {
          type: 'dining',
          diningSessionId: context.diningSessionId,
          ...(context.tableLabel ? { tableLabel: context.tableLabel } : {}),
          ...(context.draftItemCount !== undefined
            ? { draftItemCount: context.draftItemCount }
            : {}),
        };

  return cachedContext;
}

function clearPersistedOrderingContext(): void {
  if (typeof window === 'undefined') {
    return;
  }

  window.sessionStorage.removeItem(STORAGE_KEY);
}

export function readOrderingContext(): OrderingContext {
  if (typeof window === 'undefined') {
    return RETAIL_ORDERING_CONTEXT;
  }

  try {
    const raw = window.sessionStorage.getItem(STORAGE_KEY);

    if (raw === cachedRaw && cachedRaw !== undefined) {
      return cachedContext;
    }

    if (!raw) {
      return rememberSnapshot(null, RETAIL_ORDERING_CONTEXT);
    }

    const parsed: unknown = JSON.parse(raw);
    const normalized = normalizeOrderingContext(parsed);

    if (normalized.type === 'retail') {
      // Stale/incomplete dining payload — drop it so retail stays usable.
      clearPersistedOrderingContext();

      return rememberSnapshot(null, RETAIL_ORDERING_CONTEXT);
    }

    return rememberSnapshot(raw, normalized);
  } catch {
    clearPersistedOrderingContext();

    return rememberSnapshot(null, RETAIL_ORDERING_CONTEXT);
  }
}

export function writeOrderingContext(context: OrderingContext): void {
  if (typeof window === 'undefined') {
    return;
  }

  const normalized = normalizeOrderingContext(context);

  if (normalized.type === 'retail') {
    clearPersistedOrderingContext();
    rememberSnapshot(null, RETAIL_ORDERING_CONTEXT);
    window.dispatchEvent(new Event(CHANGE_EVENT));

    return;
  }

  // Preserve tableLabel / draft badge when callers only reaffirm dining mode.
  const previous = cachedContext.type === 'dining' ? cachedContext : readOrderingContext();
  const merged =
    previous.type === 'dining' && previous.diningSessionId === normalized.diningSessionId
      ? {
          type: 'dining' as const,
          diningSessionId: normalized.diningSessionId,
          tableLabel: normalized.tableLabel ?? previous.tableLabel,
          draftItemCount:
            normalized.draftItemCount !== undefined
              ? normalized.draftItemCount
              : previous.draftItemCount,
        }
      : normalized;

  const payload = JSON.stringify({
    type: 'dining',
    diningSessionId: merged.diningSessionId,
    ...(merged.tableLabel ? { tableLabel: merged.tableLabel } : {}),
    ...(merged.draftItemCount !== undefined ? { draftItemCount: merged.draftItemCount } : {}),
  });

  window.sessionStorage.setItem(STORAGE_KEY, payload);
  rememberSnapshot(payload, merged);
  window.dispatchEvent(new Event(CHANGE_EVENT));
}

export function clearOrderingContext(): void {
  writeOrderingContext(RETAIL_ORDERING_CONTEXT);
}

export function isDiningOrderingContext(
  context: OrderingContext = readOrderingContext(),
): context is Extract<OrderingContext, { type: 'dining' }> {
  return context.type === 'dining' && isNonEmptyString(context.diningSessionId);
}

export function diningMenuPath(sessionId: string | number): string {
  return `/dining/sessions/${sessionId}/menu`;
}

export function diningSessionPath(sessionId: string | number): string {
  return `/dining/sessions/${sessionId}`;
}

/** Subscribe to ordering-context changes (same-tab + storage). */
export function subscribeOrderingContext(listener: () => void): () => void {
  if (typeof window === 'undefined') {
    return () => undefined;
  }

  const onChange = (): void => {
    // Force re-read on next getSnapshot (cross-tab storage may change raw).
    cachedRaw = undefined;
    listener();
  };

  window.addEventListener(CHANGE_EVENT, onChange);
  window.addEventListener('storage', onChange);

  return () => {
    window.removeEventListener(CHANGE_EVENT, onChange);
    window.removeEventListener('storage', onChange);
  };
}

/** Test helper: reset in-memory snapshot cache. */
export function resetOrderingContextCacheForTests(): void {
  cachedRaw = undefined;
  cachedContext = RETAIL_ORDERING_CONTEXT;
}
