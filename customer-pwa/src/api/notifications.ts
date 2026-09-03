import { ApiEnvelope, get, post } from './client';

export type OperationalNotificationSubject = {
  type: string;
  id: number;
} | null;

export type OperationalNotificationMetrics = {
  delivery_delay_seconds: number | null;
  first_seen_delay_seconds: number | null;
  acknowledge_delay_seconds: number | null;
  action_start_delay_seconds: number | null;
  action_completion_delay_seconds: number | null;
  resolution_delay_seconds: number | null;
};

export type OperationalNotificationItem = {
  id: number;
  uuid: string;
  recipient_id: number;
  type: string;
  category: string;
  priority: string;
  title: string;
  message: string;
  action_required: boolean;
  action_code: string | null;
  action_url: string | null;
  subject: OperationalNotificationSubject;
  resolved_at: string | null;
  broadcast_at: string | null;
  delivered_at: string | null;
  first_seen_at: string | null;
  read_at: string | null;
  acknowledged_at: string | null;
  action_started_at: string | null;
  action_completed_at: string | null;
  reminder_count: number;
  last_reminded_at: string | null;
  created_at: string | null;
  metrics: OperationalNotificationMetrics;
};

export type OperationalNotificationCounts = {
  unread_count: number;
  action_required_count: number;
};

export type OperationalNotificationListResult = {
  items: OperationalNotificationItem[];
  counts: OperationalNotificationCounts;
};

type NotificationListEnvelope = ApiEnvelope<OperationalNotificationItem[]> & {
  meta?: OperationalNotificationCounts & Record<string, unknown>;
};

function asCounts(meta: Record<string, unknown> | undefined): OperationalNotificationCounts {
  return {
    unread_count: Number(meta?.unread_count ?? 0),
    action_required_count: Number(meta?.action_required_count ?? 0),
  };
}

function toListResult(response: NotificationListEnvelope): OperationalNotificationListResult {
  return {
    items: Array.isArray(response.data) ? response.data : [],
    counts: asCounts(response.meta),
  };
}

export async function fetchNotifications(limit = 30): Promise<OperationalNotificationListResult> {
  const params = new URLSearchParams({ limit: String(limit) });
  const response = await get<NotificationListEnvelope>(`/notifications?${params.toString()}`);

  return toListResult(response);
}

export async function fetchActionRequiredNotifications(limit = 30): Promise<OperationalNotificationListResult> {
  const params = new URLSearchParams({ limit: String(limit) });
  const response = await get<NotificationListEnvelope>(`/notifications/action-required?${params.toString()}`);

  return toListResult(response);
}

export async function markNotificationDelivered(recipientId: number): Promise<OperationalNotificationItem> {
  const response = await post<ApiEnvelope<OperationalNotificationItem>, Record<string, never>>(
    `/notifications/${recipientId}/delivered`,
    {},
  );

  return response.data;
}

export async function markNotificationSeen(recipientId: number): Promise<OperationalNotificationItem> {
  const response = await post<ApiEnvelope<OperationalNotificationItem>, Record<string, never>>(
    `/notifications/${recipientId}/seen`,
    {},
  );

  return response.data;
}

export async function markNotificationRead(recipientId: number): Promise<OperationalNotificationItem> {
  const response = await post<ApiEnvelope<OperationalNotificationItem>, Record<string, never>>(
    `/notifications/${recipientId}/read`,
    {},
  );

  return response.data;
}

export async function acknowledgeNotification(recipientId: number): Promise<OperationalNotificationItem> {
  const response = await post<ApiEnvelope<OperationalNotificationItem>, Record<string, never>>(
    `/notifications/${recipientId}/acknowledge`,
    {},
  );

  return response.data;
}

export async function recordNotificationReminder(recipientId: number): Promise<OperationalNotificationItem> {
  const response = await post<ApiEnvelope<OperationalNotificationItem>, Record<string, never>>(
    `/notifications/${recipientId}/reminded`,
    {},
  );

  return response.data;
}
