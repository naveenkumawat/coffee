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
  'escalation.no_barista_online',
  'escalation.no_chef_online',
  'escalation.no_waiter_online',
  'inventory.refill_requested',
  'inventory.stock_out',
]);

/** Customer informational types that get a one-time strong toast + sound (no 30s repeat). */
export const CUSTOMER_STRONG_ALERT_TYPES = new Set([
  'customer.order.ready',
  'customer.payment.rejected',
  'customer.order.cancelled',
  'customer.order.rejected',
  'customer.dining.ready',
]);

export const CHANNEL_NAME = 'coffee-ops-notifications';
export const STORAGE_LEADER_KEY = 'coffee.ops.notifications.leader';
export const SYNC_COALESCE_MS = 400;
