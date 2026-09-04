/**
 * Explicit customer ordering context.
 * Dining mode routes customized products into the dining round draft (never the retail cart).
 */
export type OrderingContext =
  | { type: 'retail' }
  | { type: 'dining'; diningSessionId: string; tableLabel?: string };

const STORAGE_KEY = 'coffee.ordering_context.v1';
const CHANGE_EVENT = 'coffee:ordering-context';

export function readOrderingContext(): OrderingContext {
  if (typeof window === 'undefined') {
    return { type: 'retail' };
  }

  try {
    const raw = window.sessionStorage.getItem(STORAGE_KEY);
    if (!raw) {
      return { type: 'retail' };
    }

    const parsed = JSON.parse(raw) as OrderingContext;
    if (parsed?.type === 'dining' && parsed.diningSessionId) {
      return {
        type: 'dining',
        diningSessionId: String(parsed.diningSessionId),
        tableLabel: parsed.tableLabel ? String(parsed.tableLabel) : undefined,
      };
    }
  } catch {
    // fall through
  }

  return { type: 'retail' };
}

export function writeOrderingContext(context: OrderingContext): void {
  if (typeof window === 'undefined') {
    return;
  }

  if (context.type === 'retail') {
    window.sessionStorage.removeItem(STORAGE_KEY);
    window.dispatchEvent(new Event(CHANGE_EVENT));

    return;
  }

  window.sessionStorage.setItem(
    STORAGE_KEY,
    JSON.stringify({
      type: 'dining',
      diningSessionId: String(context.diningSessionId),
      ...(context.tableLabel ? { tableLabel: String(context.tableLabel) } : {}),
    }),
  );
  window.dispatchEvent(new Event(CHANGE_EVENT));
}

export function clearOrderingContext(): void {
  writeOrderingContext({ type: 'retail' });
}

export function isDiningOrderingContext(
  context: OrderingContext = readOrderingContext(),
): context is Extract<OrderingContext, { type: 'dining' }> {
  return context.type === 'dining';
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

  const onChange = (): void => listener();
  window.addEventListener(CHANGE_EVENT, onChange);
  window.addEventListener('storage', onChange);

  return () => {
    window.removeEventListener(CHANGE_EVENT, onChange);
    window.removeEventListener('storage', onChange);
  };
}
