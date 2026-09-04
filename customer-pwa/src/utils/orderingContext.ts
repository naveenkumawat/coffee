/**
 * Explicit customer ordering context.
 * Dining mode routes customized products into the dining round draft (never the retail cart).
 */
export type OrderingContext =
  | { type: 'retail' }
  | { type: 'dining'; diningSessionId: string };

const STORAGE_KEY = 'coffee.ordering_context.v1';

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

    return;
  }

  window.sessionStorage.setItem(STORAGE_KEY, JSON.stringify(context));
}

export function clearOrderingContext(): void {
  writeOrderingContext({ type: 'retail' });
}

export function diningMenuPath(sessionId: string | number): string {
  return `/dining/sessions/${sessionId}/menu`;
}

export function diningSessionPath(sessionId: string | number): string {
  return `/dining/sessions/${sessionId}`;
}
