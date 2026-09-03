/** @typedef {import('./types').OpsNotification} OpsNotification */

export const REMINDER_INTERVAL_MS = 30_000;
export const LEADER_HEARTBEAT_MS = 2_000;
export const LEADER_STALE_MS = 5_000;
export const SYNC_ABSENCE_MS = 15_000;

export const REMINDER_ELIGIBLE_TYPES = new Set([
    'order.requires_attention',
    'order.requires_acceptance',
    'order.payment_proof_review',
    'preparation.ticket_pending',
    'dining.ready_to_serve',
]);

export const CHANNEL_NAME = 'coffee-ops-notifications';
export const STORAGE_LEADER_KEY = 'coffee.ops.notifications.leader';
export const STORAGE_EVENT_KEY = 'coffee.ops.notifications.event';
