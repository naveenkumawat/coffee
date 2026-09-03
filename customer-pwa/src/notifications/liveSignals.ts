import { OperationalNotificationItem } from '../api/notifications';

export type LiveCanonicalSignal = {
  type: string;
  subject: { type: string; id: number } | null;
  action_url: string | null;
  recipient_id: number;
};

type Handler = (signal: LiveCanonicalSignal) => void;

const handlers = new Set<Handler>();

/**
 * Pub/sub for customer order/dining live reconciliation.
 * Socket payload is a signal only — subscribers refetch canonical REST state.
 */
export function subscribeLiveSignals(handler: Handler): () => void {
  handlers.add(handler);

  return () => {
    handlers.delete(handler);
  };
}

export function emitLiveSignal(signal: LiveCanonicalSignal): void {
  handlers.forEach((handler) => {
    try {
      handler(signal);
    } catch {
      // Never crash UI from a malformed subscriber.
    }
  });
}

export function isCustomerNotificationType(type: string | undefined): boolean {
  return typeof type === 'string' && type.startsWith('customer.');
}

export function toLiveSignal(
  item: Pick<OperationalNotificationItem, 'type' | 'subject' | 'action_url' | 'recipient_id'>,
): LiveCanonicalSignal | null {
  if (!isCustomerNotificationType(item.type)) {
    return null;
  }

  return {
    type: item.type,
    subject: item.subject ?? null,
    action_url: item.action_url ?? null,
    recipient_id: item.recipient_id,
  };
}
