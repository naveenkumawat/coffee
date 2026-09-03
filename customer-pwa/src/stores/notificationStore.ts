import { create } from 'zustand';
import {
  acknowledgeNotification,
  fetchActionRequiredNotifications,
  fetchNotifications,
  markNotificationDelivered,
  markNotificationRead,
  markNotificationSeen,
  OperationalNotificationItem,
} from '../api/notifications';

interface NotificationState {
  status: 'idle' | 'loading' | 'ready' | 'error';
  items: OperationalNotificationItem[];
  unreadCount: number;
  actionRequiredCount: number;
  error: string | null;
  loadRecent: (limit?: number) => Promise<void>;
  loadActionRequired: (limit?: number) => Promise<void>;
  upsertFromRealtime: (item: Partial<OperationalNotificationItem> & { recipient_id: number }) => void;
  markDelivered: (recipientId: number) => Promise<void>;
  markSeen: (recipientId: number) => Promise<void>;
  markRead: (recipientId: number) => Promise<void>;
  acknowledge: (recipientId: number) => Promise<void>;
  reset: () => void;
}

function mergeItem(
  items: OperationalNotificationItem[],
  next: OperationalNotificationItem,
): OperationalNotificationItem[] {
  const without = items.filter((item) => item.recipient_id !== next.recipient_id);

  return [next, ...without].slice(0, 50);
}

function recount(items: OperationalNotificationItem[]): Pick<NotificationState, 'unreadCount' | 'actionRequiredCount'> {
  return {
    unreadCount: items.filter((item) => !item.read_at).length,
    actionRequiredCount: items.filter(
      (item) => item.action_required && !item.acknowledged_at && !item.resolved_at,
    ).length,
  };
}

export const useNotificationStore = create<NotificationState>((set, get) => ({
  status: 'idle',
  items: [],
  unreadCount: 0,
  actionRequiredCount: 0,
  error: null,
  loadRecent: async (limit = 30) => {
    set({ status: 'loading', error: null });

    try {
      const result = await fetchNotifications(limit);
      set({
        status: 'ready',
        items: result.items,
        unreadCount: result.counts.unread_count,
        actionRequiredCount: result.counts.action_required_count,
      });
    } catch (error) {
      set({
        status: 'error',
        error: error instanceof Error ? error.message : 'Unable to load notifications.',
      });
    }
  },
  loadActionRequired: async (limit = 30) => {
    set({ status: 'loading', error: null });

    try {
      const result = await fetchActionRequiredNotifications(limit);
      set({
        status: 'ready',
        items: result.items,
        unreadCount: result.counts.unread_count,
        actionRequiredCount: result.counts.action_required_count,
      });
    } catch (error) {
      set({
        status: 'error',
        error: error instanceof Error ? error.message : 'Unable to load action-required notifications.',
      });
    }
  },
  upsertFromRealtime: (payload) => {
    const existing = get().items.find((item) => item.recipient_id === payload.recipient_id);
    const next = {
      id: payload.id ?? existing?.id ?? 0,
      uuid: payload.uuid ?? existing?.uuid ?? '',
      recipient_id: payload.recipient_id,
      type: payload.type ?? existing?.type ?? 'ops.unknown',
      category: payload.category ?? existing?.category ?? 'system',
      priority: payload.priority ?? existing?.priority ?? 'normal',
      title: payload.title ?? existing?.title ?? 'Notification',
      message: payload.message ?? existing?.message ?? '',
      action_required: payload.action_required ?? existing?.action_required ?? false,
      action_code: payload.action_code ?? existing?.action_code ?? null,
      action_url: payload.action_url ?? existing?.action_url ?? null,
      subject: payload.subject ?? existing?.subject ?? null,
      resolved_at: payload.resolved_at ?? existing?.resolved_at ?? null,
      broadcast_at: payload.broadcast_at ?? existing?.broadcast_at ?? null,
      delivered_at: payload.delivered_at ?? existing?.delivered_at ?? null,
      first_seen_at: payload.first_seen_at ?? existing?.first_seen_at ?? null,
      read_at: payload.read_at ?? existing?.read_at ?? null,
      acknowledged_at: payload.acknowledged_at ?? existing?.acknowledged_at ?? null,
      action_started_at: payload.action_started_at ?? existing?.action_started_at ?? null,
      action_completed_at: payload.action_completed_at ?? existing?.action_completed_at ?? null,
      reminder_count: payload.reminder_count ?? existing?.reminder_count ?? 0,
      last_reminded_at: payload.last_reminded_at ?? existing?.last_reminded_at ?? null,
      created_at: payload.created_at ?? existing?.created_at ?? null,
      metrics: payload.metrics ?? existing?.metrics ?? {
        delivery_delay_seconds: null,
        first_seen_delay_seconds: null,
        acknowledge_delay_seconds: null,
        action_start_delay_seconds: null,
        action_completion_delay_seconds: null,
        resolution_delay_seconds: null,
      },
    } satisfies OperationalNotificationItem;

    const items = mergeItem(get().items, next);
    set({ items, ...recount(items), status: 'ready' });
  },
  markDelivered: async (recipientId) => {
    const next = await markNotificationDelivered(recipientId);
    const items = mergeItem(get().items, next);
    set({ items, ...recount(items) });
  },
  markSeen: async (recipientId) => {
    const next = await markNotificationSeen(recipientId);
    const items = mergeItem(get().items, next);
    set({ items, ...recount(items) });
  },
  markRead: async (recipientId) => {
    const next = await markNotificationRead(recipientId);
    const items = mergeItem(get().items, next);
    set({ items, ...recount(items) });
  },
  acknowledge: async (recipientId) => {
    const next = await acknowledgeNotification(recipientId);
    const items = mergeItem(get().items, next);
    set({ items, ...recount(items) });
  },
  reset: () => set({
    status: 'idle',
    items: [],
    unreadCount: 0,
    actionRequiredCount: 0,
    error: null,
  }),
}));
