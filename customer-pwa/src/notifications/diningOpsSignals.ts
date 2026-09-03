export type DiningOpsPayload = {
  event_id?: string | null;
  type?: string | null;
  session_id?: number | null;
  table_id?: number | null;
  order_id?: number | null;
  state?: string | null;
  updated_at?: string | null;
};

type Handler = (payload: DiningOpsPayload) => void;

const handlers = new Set<Handler>();

/**
 * Pub/sub for dining/table scoped realtime signals (R1.6).
 * Socket is advisory — subscribers refetch canonical REST state.
 */
export function subscribeDiningOps(handler: Handler): () => void {
  handlers.add(handler);

  return () => {
    handlers.delete(handler);
  };
}

export function emitDiningOps(payload: DiningOpsPayload): void {
  handlers.forEach((handler) => {
    try {
      handler(payload);
    } catch {
      // Never crash UI from a malformed subscriber.
    }
  });
}

export function normalizeDiningOpsPayload(raw: Record<string, unknown> | null | undefined): DiningOpsPayload | null {
  if (!raw || typeof raw !== 'object') {
    return null;
  }

  const type = typeof raw.type === 'string' ? raw.type : null;
  if (!type) {
    return null;
  }

  return {
    event_id: typeof raw.event_id === 'string' ? raw.event_id : null,
    type,
    session_id: typeof raw.session_id === 'number' ? raw.session_id : Number(raw.session_id) || null,
    table_id: typeof raw.table_id === 'number' ? raw.table_id : Number(raw.table_id) || null,
    order_id: typeof raw.order_id === 'number' ? raw.order_id : Number(raw.order_id) || null,
    state: typeof raw.state === 'string' ? raw.state : null,
    updated_at: typeof raw.updated_at === 'string' ? raw.updated_at : null,
  };
}
