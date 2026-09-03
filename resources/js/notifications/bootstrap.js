import './notifications.css';
import { notificationsApi } from './api';
import { createNotificationStore } from './store';
import { createNotificationSoundManager } from './sound';
import { createTabLeader } from './tabLeader';
import { createActionReminderManager } from './reminder';
import { createNotificationUi } from './ui';
import { normalizePayload, isActionable, isReminderEligible } from './normalize';
import { SYNC_ABSENCE_MS, SYNC_COALESCE_MS } from './config';
import { createEventDedupe, createSyncCoalescer } from './eventDedupe';

function createClient() {
    const store = createNotificationStore();
    const sound = createNotificationSoundManager();
    const leader = createTabLeader();
    const deliveredAcked = new Set();
    const eventDedupe = createEventDedupe('ops');
    let hiddenSince = null;

    const ui = createNotificationUi({
        store,
        onSeen: async (recipientId) => {
            try {
                const payload = await notificationsApi.seen(recipientId);
                const item = normalizePayload(payload?.data);
                if (item) {
                    store.upsert(item);
                }
            } catch {
                // ignore
            }
        },
        onMarkRead: async (recipientId) => {
            try {
                const payload = await notificationsApi.read(recipientId);
                const item = normalizePayload(payload?.data);
                if (item) {
                    store.upsert(item);
                }
            } catch {
                // ignore
            }
        },
        onAcknowledge: async (recipientId) => {
            try {
                const payload = await notificationsApi.acknowledge(recipientId);
                const item = normalizePayload(payload?.data);
                if (item) {
                    store.upsert(item);
                }
            } catch {
                // ignore
            }
        },
        onOpenTarget: async (item) => {
            try {
                const payload = await notificationsApi.read(item.recipient_id);
                const next = normalizePayload(payload?.data);
                if (next) {
                    store.upsert(next);
                }
            } catch {
                // ignore
            }

            if (item.action_url) {
                window.location.assign(item.action_url);
            }
        },
    });

    const reminder = createActionReminderManager({
        store,
        leader,
        sound,
        presentReminder: (item) => {
            if (!leader.isLeader()) {
                return;
            }
            ui.showToast(item, { reminder: true });
        },
        recordReminder: async (recipientId) => {
            const payload = await notificationsApi.reminded(recipientId);

            return normalizePayload(payload?.data);
        },
    });

    async function syncNow() {
        store.setState({ status: 'syncing', error: null });

        try {
            const [listPayload, actionPayload] = await Promise.all([
                notificationsApi.list(40),
                notificationsApi.actionRequired(40),
            ]);

            const listItems = Array.isArray(listPayload?.data)
                ? listPayload.data.map(normalizePayload).filter(Boolean)
                : [];
            const actionItems = Array.isArray(actionPayload?.data)
                ? actionPayload.data.map(normalizePayload).filter(Boolean)
                : [];

            const byId = new Map();
            [...actionItems, ...listItems].forEach((item) => {
                byId.set(item.recipient_id, item);
            });

            store.reconcile([...byId.values()], {
                unread_count: listPayload?.meta?.unread_count,
                action_required_count: listPayload?.meta?.action_required_count
                    ?? actionPayload?.meta?.action_required_count,
            });
            reminder.syncFromStore();
        } catch (error) {
            store.setState({
                status: 'error',
                error: error instanceof Error ? error.message : 'Sync failed',
            });
        }
    }

    const syncCoalescer = createSyncCoalescer(syncNow, SYNC_COALESCE_MS);
    const sync = () => syncCoalescer.request();

    async function handleRealtime(raw) {
        const item = normalizePayload(raw);
        if (!item) {
            return;
        }

        const dedupeKey = item.uuid || `${item.id}:${item.recipient_id}:${item.created_at || ''}`;
        const isNew = eventDedupe.claim(dedupeKey);

        store.upsert(item);

        if (!deliveredAcked.has(item.recipient_id)) {
            deliveredAcked.add(item.recipient_id);
            try {
                const payload = await notificationsApi.delivered(item.recipient_id);
                const next = normalizePayload(payload?.data);
                if (next) {
                    store.upsert(next);
                }
            } catch {
                deliveredAcked.delete(item.recipient_id);
            }
        }

        if (isNew && leader.isLeader()) {
            ui.showToast(item);
            if (isActionable(item)) {
                await sound.play();
            }
        }

        if (isReminderEligible(item)) {
            reminder.armImmediate(item);
        }
    }

    function bindRealtime() {
        window.addEventListener('coffee:operational-notification', (event) => {
            void handleRealtime(event.detail);
        });
    }

    function bindLifecycle() {
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                hiddenSince = Date.now();

                return;
            }

            const away = hiddenSince ? Date.now() - hiddenSince : 0;
            hiddenSince = null;
            if (away >= SYNC_ABSENCE_MS) {
                sync();
            }
        });

        window.addEventListener('online', () => {
            sync();
        });

        const indicator = document.getElementById('coffee-realtime-indicator');
        if (indicator) {
            let lastSyncState = null;
            const observer = new MutationObserver(() => {
                const state = indicator.dataset.state || 'idle';
                store.setState({ connectionState: state });
                if ((state === 'connected' || state === 'reconnected') && state !== lastSyncState) {
                    lastSyncState = state;
                    sync();
                }
            });
            observer.observe(indicator, { attributes: true, attributeFilter: ['data-state'] });
        }
    }

    bindRealtime();
    bindLifecycle();
    sync();

    return {
        store,
        sync,
        handleRealtime,
        ui,
        reminder,
        leader,
        sound,
    };
}

const ready = () => {
    if (!document.getElementById('coffee-ops-bell')) {
        window.__COFFEE_NOTIFICATIONS__ = notificationsApi;

        return;
    }

    window.__COFFEE_OPS_NOTIFICATION_CLIENT__ = createClient();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', ready);
} else {
    ready();
}
