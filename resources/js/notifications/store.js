import { isActionable } from './normalize';

/**
 * @typedef {object} NotificationStoreState
 * @property {import('./types').OpsNotification[]} items
 * @property {number} unreadCount
 * @property {number} actionRequiredCount
 * @property {'idle'|'syncing'|'ready'|'error'} status
 * @property {string|null} lastSyncAt
 * @property {string|null} error
 * @property {string} connectionState
 */

export function createNotificationStore() {
    /** @type {NotificationStoreState} */
    let state = {
        items: [],
        unreadCount: 0,
        actionRequiredCount: 0,
        status: 'idle',
        lastSyncAt: null,
        error: null,
        connectionState: 'idle',
    };

    /** @type {Set<(s: NotificationStoreState) => void>} */
    const listeners = new Set();

    function emit() {
        listeners.forEach((listener) => listener(getState()));
    }

    function recount(items) {
        return {
            unreadCount: items.filter((item) => !item.read_at).length,
            actionRequiredCount: items.filter((item) => isActionable(item)).length,
        };
    }

    function getState() {
        return { ...state, items: [...state.items] };
    }

    /**
     * @param {Partial<NotificationStoreState>} patch
     */
    function setState(patch) {
        state = { ...state, ...patch };
        emit();
    }

    /**
     * @param {import('./types').OpsNotification} next
     */
    function upsert(next) {
        const without = state.items.filter((item) => item.recipient_id !== next.recipient_id);
        const items = [next, ...without].slice(0, 80);
        setState({ items, ...recount(items), status: 'ready' });
    }

    /**
     * @param {import('./types').OpsNotification[]} serverItems
     * @param {{ unread_count?: number, action_required_count?: number }} [counts]
     */
    function reconcile(serverItems, counts = {}) {
        const byId = new Map(state.items.map((item) => [item.recipient_id, item]));
        const merged = serverItems.map((item) => {
            const previous = byId.get(item.recipient_id);

            return previous ? { ...previous, ...item } : item;
        });

        // Keep any local realtime items not yet in server page (rare race).
        serverItems.forEach((item) => byId.delete(item.recipient_id));
        const leftovers = [...byId.values()].filter((item) => isActionable(item));
        const items = [...merged, ...leftovers].slice(0, 80);
        const localCounts = recount(items);

        setState({
            items,
            unreadCount: Number(counts.unread_count ?? localCounts.unreadCount),
            actionRequiredCount: Number(counts.action_required_count ?? localCounts.actionRequiredCount),
            status: 'ready',
            lastSyncAt: new Date().toISOString(),
            error: null,
        });
    }

    return {
        getState,
        setState,
        upsert,
        reconcile,
        subscribe(listener) {
            listeners.add(listener);
            listener(getState());

            return () => listeners.delete(listener);
        },
        actionableItems() {
            return state.items.filter((item) => isActionable(item));
        },
    };
}
