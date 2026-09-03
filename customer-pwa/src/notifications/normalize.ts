import { OperationalNotificationItem } from '../api/notifications';
import { CUSTOMER_STRONG_ALERT_TYPES, REMINDER_ELIGIBLE_TYPES } from './config';

export function isActionable(item: OperationalNotificationItem): boolean {
  return Boolean(item.action_required) && !item.resolved_at;
}

export function isReminderEligible(item: OperationalNotificationItem): boolean {
  return isActionable(item) && REMINDER_ELIGIBLE_TYPES.has(item.type);
}

export function isCustomerStrongAlert(type: string | undefined): boolean {
  return typeof type === 'string' && CUSTOMER_STRONG_ALERT_TYPES.has(type);
}

export function sortActionRequired(items: OperationalNotificationItem[]): OperationalNotificationItem[] {
  const priorityRank: Record<string, number> = { critical: 0, high: 1, normal: 2, low: 3 };

  return [...items].sort((a, b) => {
    const pa = priorityRank[a.priority] ?? 2;
    const pb = priorityRank[b.priority] ?? 2;
    if (pa !== pb) {
      return pa - pb;
    }

    return Date.parse(a.created_at ?? '') - Date.parse(b.created_at ?? '');
  });
}

export function formatElapsed(iso: string | null): string {
  if (!iso) {
    return '';
  }

  const seconds = Math.floor((Date.now() - Date.parse(iso)) / 1000);
  if (!Number.isFinite(seconds) || seconds < 0) {
    return '';
  }

  if (seconds < 60) {
    return `${seconds}s waiting`;
  }

  const minutes = Math.floor(seconds / 60);
  if (minutes < 60) {
    return `${minutes}m waiting`;
  }

  return `${Math.floor(minutes / 60)}h waiting`;
}

function normalizeSubject(value: unknown): OperationalNotificationItem['subject'] {
  if (!value || typeof value !== 'object') {
    return null;
  }

  const record = value as Record<string, unknown>;
  const type = typeof record.type === 'string' ? record.type : null;
  const id = Number(record.id);
  if (!type || !Number.isFinite(id) || id <= 0) {
    return null;
  }

  return { type, id };
}

export function normalizeRealtimePayload(payload: Record<string, unknown>): Partial<OperationalNotificationItem> & { recipient_id: number } | null {
  const recipientId = Number(payload.recipient_id);
  if (!Number.isFinite(recipientId) || recipientId <= 0) {
    return null;
  }

  return {
    recipient_id: recipientId,
    id: Number(payload.id) || undefined,
    uuid: typeof payload.uuid === 'string' ? payload.uuid : undefined,
    type: typeof payload.type === 'string' ? payload.type : undefined,
    category: typeof payload.category === 'string' ? payload.category : undefined,
    priority: typeof payload.priority === 'string' ? payload.priority : undefined,
    title: typeof payload.title === 'string' ? payload.title : undefined,
    message: typeof payload.message === 'string' ? payload.message : undefined,
    action_required: Boolean(payload.action_required),
    action_code: typeof payload.action_code === 'string' ? payload.action_code : null,
    action_url: typeof payload.action_url === 'string' ? payload.action_url : null,
    subject: normalizeSubject(payload.subject),
    created_at: typeof payload.created_at === 'string' ? payload.created_at : null,
  };
}
