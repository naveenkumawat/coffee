/**
 * Customer ordering context.
 *
 * Active dining session and current ordering mode are independent:
 * - diningSession: seated Table T1 session (if any)
 * - mode: where Menu/product adds go (dining draft vs retail Takeaway cart)
 *
 * Never merge Takeaway cart items into the dining bill.
 */

export type OrderingMode = 'dining' | 'takeaway';

export type ActiveDiningSession = {
  diningSessionId: string;
  tableLabel?: string;
  /** Quantity of items in the dining next-order draft (session UI only — not Cart badge). */
  draftItemCount?: number;
};

export type OrderingContext = {
  mode: OrderingMode;
  diningSession: ActiveDiningSession | null;
};

/** Accepts legacy `{ type: 'dining' | 'retail' }` and new `{ mode, diningSession }` writes. */
export type OrderingContextWrite = Partial<{
  mode: OrderingMode;
  diningSession: ActiveDiningSession | null;
  /** @deprecated Prefer mode + diningSession */
  type: 'dining' | 'retail';
  diningSessionId: string | number;
  tableLabel: string;
  draftItemCount: number;
}>;

const STORAGE_KEY = 'coffee.ordering_context.v2';
const LEGACY_STORAGE_KEY = 'coffee.ordering_context.v1';
const CHANGE_EVENT = 'coffee:ordering-context';

/** Stable takeaway snapshot — required for useSyncExternalStore getSnapshot caching. */
export const RETAIL_ORDERING_CONTEXT: OrderingContext = Object.freeze({
  mode: 'takeaway',
  diningSession: null,
});

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

function normalizeSessionId(rawId: unknown): string {
  if (typeof rawId === 'number' && Number.isFinite(rawId)) {
    return String(rawId);
  }

  if (isNonEmptyString(rawId)) {
    return String(rawId).trim();
  }

  return '';
}

function normalizeTableLabel(rawLabel: unknown): string | undefined {
  if (typeof rawLabel === 'string' && rawLabel.trim()) {
    return rawLabel.trim();
  }

  if (
    typeof rawLabel === 'object' &&
    rawLabel !== null &&
    isNonEmptyString((rawLabel as { label?: unknown }).label)
  ) {
    return String((rawLabel as { label: string }).label).trim();
  }

  return undefined;
}

function normalizeActiveDiningSession(value: unknown): ActiveDiningSession | null {
  if (!value || typeof value !== 'object') {
    return null;
  }

  const record = value as Record<string, unknown>;
  const diningSessionId = normalizeSessionId(
    record.diningSessionId ?? record.sessionId ?? record.dining_session_id,
  );

  if (!diningSessionId) {
    return null;
  }

  const tableLabel = normalizeTableLabel(record.tableLabel ?? record.table_label ?? record.table);
  const draftItemCount = normalizeDraftItemCount(
    record.draftItemCount ?? record.draft_item_count ?? record.draftCount,
  );

  return {
    diningSessionId,
    ...(tableLabel ? { tableLabel } : {}),
    ...(draftItemCount !== undefined ? { draftItemCount } : {}),
  };
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
 * Normalize persisted / in-memory shapes. Invalid dining session data
 * becomes takeaway with no session (callers may clear storage separately).
 */
export function normalizeOrderingContext(value: unknown): OrderingContext {
  if (!value || typeof value !== 'object') {
    return RETAIL_ORDERING_CONTEXT;
  }

  const record = value as Record<string, unknown>;

  // New shape
  if (record.mode === 'dining' || record.mode === 'takeaway' || 'diningSession' in record) {
    const diningSession =
      record.diningSession === null
        ? null
        : normalizeActiveDiningSession(record.diningSession ?? record);

    const mode: OrderingMode =
      record.mode === 'dining' && diningSession
        ? 'dining'
        : record.mode === 'takeaway'
          ? 'takeaway'
          : diningSession
            ? 'dining'
            : 'takeaway';

    if (!diningSession) {
      return RETAIL_ORDERING_CONTEXT;
    }

    return {
      mode: mode === 'dining' ? 'dining' : 'takeaway',
      diningSession,
    };
  }

  // Legacy retail
  if (record.type === 'retail') {
    return RETAIL_ORDERING_CONTEXT;
  }

  // Legacy dining (type: 'dining' with session id)
  if (record.type === 'dining') {
    const diningSession = normalizeActiveDiningSession(record);

    if (!diningSession) {
      return RETAIL_ORDERING_CONTEXT;
    }

    return {
      mode: 'dining',
      diningSession,
    };
  }

  return RETAIL_ORDERING_CONTEXT;
}

function sessionsEqual(a: ActiveDiningSession | null, b: ActiveDiningSession | null): boolean {
  if (a === b) {
    return true;
  }

  if (!a || !b) {
    return false;
  }

  return (
    a.diningSessionId === b.diningSessionId &&
    a.tableLabel === b.tableLabel &&
    (a.draftItemCount ?? 0) === (b.draftItemCount ?? 0)
  );
}

function contextsEqual(a: OrderingContext, b: OrderingContext): boolean {
  return a.mode === b.mode && sessionsEqual(a.diningSession, b.diningSession);
}

function freezeContext(context: OrderingContext): OrderingContext {
  if (!context.diningSession) {
    return RETAIL_ORDERING_CONTEXT;
  }

  return {
    mode: context.mode === 'dining' ? 'dining' : 'takeaway',
    diningSession: {
      diningSessionId: context.diningSession.diningSessionId,
      ...(context.diningSession.tableLabel
        ? { tableLabel: context.diningSession.tableLabel }
        : {}),
      ...(context.diningSession.draftItemCount !== undefined
        ? { draftItemCount: context.diningSession.draftItemCount }
        : {}),
    },
  };
}

function rememberSnapshot(raw: string | null, context: OrderingContext): OrderingContext {
  if (cachedRaw === raw && contextsEqual(cachedContext, context)) {
    return cachedContext;
  }

  cachedRaw = raw;
  cachedContext = freezeContext(context);

  return cachedContext;
}

function clearPersistedOrderingContext(): void {
  if (typeof window === 'undefined') {
    return;
  }

  window.sessionStorage.removeItem(STORAGE_KEY);
  window.sessionStorage.removeItem(LEGACY_STORAGE_KEY);
}

function persistContext(context: OrderingContext): void {
  if (typeof window === 'undefined') {
    return;
  }

  if (!context.diningSession) {
    clearPersistedOrderingContext();
    rememberSnapshot(null, RETAIL_ORDERING_CONTEXT);
    window.dispatchEvent(new Event(CHANGE_EVENT));

    return;
  }

  const payload = JSON.stringify({
    mode: context.mode,
    diningSession: {
      diningSessionId: context.diningSession.diningSessionId,
      ...(context.diningSession.tableLabel
        ? { tableLabel: context.diningSession.tableLabel }
        : {}),
      ...(context.diningSession.draftItemCount !== undefined
        ? { draftItemCount: context.diningSession.draftItemCount }
        : {}),
    },
  });

  window.sessionStorage.setItem(STORAGE_KEY, payload);
  window.sessionStorage.removeItem(LEGACY_STORAGE_KEY);
  rememberSnapshot(payload, context);
  window.dispatchEvent(new Event(CHANGE_EVENT));
}

function readRawStorage(): string | null {
  if (typeof window === 'undefined') {
    return null;
  }

  return window.sessionStorage.getItem(STORAGE_KEY) ?? window.sessionStorage.getItem(LEGACY_STORAGE_KEY);
}

export function readOrderingContext(): OrderingContext {
  if (typeof window === 'undefined') {
    return RETAIL_ORDERING_CONTEXT;
  }

  try {
    const raw = readRawStorage();

    if (raw === cachedRaw && cachedRaw !== undefined) {
      return cachedContext;
    }

    if (!raw) {
      return rememberSnapshot(null, RETAIL_ORDERING_CONTEXT);
    }

    const parsed: unknown = JSON.parse(raw);
    const normalized = normalizeOrderingContext(parsed);

    if (!normalized.diningSession) {
      clearPersistedOrderingContext();

      return rememberSnapshot(null, RETAIL_ORDERING_CONTEXT);
    }

    return rememberSnapshot(raw, normalized);
  } catch {
    clearPersistedOrderingContext();

    return rememberSnapshot(null, RETAIL_ORDERING_CONTEXT);
  }
}

function mergeWrite(previous: OrderingContext, write: OrderingContextWrite): OrderingContext {
  // Explicit clear / retail
  if (write.type === 'retail' || write.diningSession === null) {
    return RETAIL_ORDERING_CONTEXT;
  }

  let diningSession = previous.diningSession;
  let mode = previous.mode;

  if (write.type === 'dining' || write.diningSessionId !== undefined || write.diningSession) {
    const nextSession = write.diningSession
      ? normalizeActiveDiningSession(write.diningSession)
      : normalizeActiveDiningSession({
          diningSessionId: write.diningSessionId,
          tableLabel: write.tableLabel,
          draftItemCount: write.draftItemCount,
        });

    if (nextSession) {
      const sameSession =
        diningSession?.diningSessionId === nextSession.diningSessionId ? diningSession : null;

      diningSession = {
        diningSessionId: nextSession.diningSessionId,
        tableLabel: nextSession.tableLabel ?? sameSession?.tableLabel,
        draftItemCount:
          nextSession.draftItemCount !== undefined
            ? nextSession.draftItemCount
            : sameSession?.draftItemCount,
      };
    }
  } else if (write.tableLabel !== undefined || write.draftItemCount !== undefined) {
    if (diningSession) {
      diningSession = {
        ...diningSession,
        ...(write.tableLabel !== undefined ? { tableLabel: write.tableLabel } : {}),
        ...(write.draftItemCount !== undefined ? { draftItemCount: write.draftItemCount } : {}),
      };
    }
  }

  if (write.mode === 'dining' || write.mode === 'takeaway') {
    mode = write.mode;
  } else if (write.type === 'dining' && !previous.diningSession) {
    // First bind to a seated session defaults into Dining mode.
    mode = 'dining';
  }
  // Refreshing session metadata must not yank Takeaway shoppers back to Dining.

  if (!diningSession) {
    return RETAIL_ORDERING_CONTEXT;
  }

  return {
    mode: mode === 'dining' ? 'dining' : 'takeaway',
    diningSession,
  };
}

export function writeOrderingContext(write: OrderingContextWrite | OrderingContext): void {
  if (typeof window === 'undefined') {
    return;
  }

  const previous =
    cachedRaw !== undefined ? cachedContext : readOrderingContext();
  const next = mergeWrite(previous, write as OrderingContextWrite);

  persistContext(next);
}

export function setOrderingMode(mode: OrderingMode): void {
  const previous = readOrderingContext();

  if (!previous.diningSession) {
    writeOrderingContext(RETAIL_ORDERING_CONTEXT);

    return;
  }

  writeOrderingContext({
    mode,
    diningSession: previous.diningSession,
  });
}

export function clearOrderingContext(): void {
  writeOrderingContext(RETAIL_ORDERING_CONTEXT);
}

/**
 * Session is no longer a usable active dining visit (paid/closed/cancelled).
 * Callers should clear dining context; Takeaway cart/mode remain usable.
 */
export function isDiningSessionTerminal(session: {
  status?: string | null;
  payment_status?: string | null;
}): boolean {
  const status = String(session.status ?? '');
  const paymentStatus = String(session.payment_status ?? '');

  return (
    status === 'closed' ||
    status === 'cancelled' ||
    status === 'paid' ||
    paymentStatus === 'confirmed'
  );
}

export function hasActiveDiningSession(
  context: OrderingContext = readOrderingContext(),
): context is OrderingContext & { diningSession: ActiveDiningSession } {
  return context.diningSession !== null && isNonEmptyString(context.diningSession.diningSessionId);
}

/** True when Menu/product adds should go to the dining draft. */
export function isDiningOrderingMode(
  context: OrderingContext = readOrderingContext(),
): boolean {
  return context.mode === 'dining' && hasActiveDiningSession(context);
}

/**
 * @deprecated Prefer hasActiveDiningSession / isDiningOrderingMode.
 * Kept for call sites that meant "active dining session exists".
 */
export function isDiningOrderingContext(
  context: OrderingContext = readOrderingContext(),
): boolean {
  return hasActiveDiningSession(context);
}

export function diningMenuPath(_sessionId?: string | number): string {
  return '/menu';
}

export function diningSessionPath(sessionId: string | number): string {
  return `/dining/sessions/${sessionId}`;
}

export function activeDiningTableLabel(
  context: OrderingContext = readOrderingContext(),
): string | null {
  if (!hasActiveDiningSession(context)) {
    return null;
  }

  return context.diningSession.tableLabel ?? null;
}

/** Subscribe to ordering-context changes (same-tab + storage). */
export function subscribeOrderingContext(listener: () => void): () => void {
  if (typeof window === 'undefined') {
    return () => undefined;
  }

  const onChange = (): void => {
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
