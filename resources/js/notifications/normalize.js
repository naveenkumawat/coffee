import { REMINDER_ELIGIBLE_TYPES } from './config';

/**
 * @param {unknown} payload
 * @returns {import('./types').OpsNotification|null}
 */
export function normalizePayload(payload) {
    if (!payload || typeof payload !== 'object') {
        return null;
    }

    const recipientId = Number(payload.recipient_id);
    if (!Number.isFinite(recipientId) || recipientId <= 0) {
        return null;
    }

    return {
        id: Number(payload.id) || 0,
        uuid: typeof payload.uuid === 'string' ? payload.uuid : '',
        recipient_id: recipientId,
        type: typeof payload.type === 'string' ? payload.type : 'unknown',
        category: typeof payload.category === 'string' ? payload.category : 'system',
        priority: typeof payload.priority === 'string' ? payload.priority : 'normal',
        title: typeof payload.title === 'string' ? payload.title : 'Notification',
        message: typeof payload.message === 'string' ? payload.message : '',
        action_required: Boolean(payload.action_required),
        action_code: typeof payload.action_code === 'string' ? payload.action_code : null,
        action_url: typeof payload.action_url === 'string' ? payload.action_url : null,
        subject: payload.subject && typeof payload.subject === 'object' ? payload.subject : null,
        resolved_at: typeof payload.resolved_at === 'string' ? payload.resolved_at : null,
        broadcast_at: typeof payload.broadcast_at === 'string' ? payload.broadcast_at : null,
        delivered_at: typeof payload.delivered_at === 'string' ? payload.delivered_at : null,
        first_seen_at: typeof payload.first_seen_at === 'string' ? payload.first_seen_at : null,
        read_at: typeof payload.read_at === 'string' ? payload.read_at : null,
        acknowledged_at: typeof payload.acknowledged_at === 'string' ? payload.acknowledged_at : null,
        reminder_count: Number(payload.reminder_count) || 0,
        last_reminded_at: typeof payload.last_reminded_at === 'string' ? payload.last_reminded_at : null,
        created_at: typeof payload.created_at === 'string' ? payload.created_at : null,
    };
}

/**
 * @param {import('./types').OpsNotification} item
 */
export function isActionable(item) {
    return Boolean(item?.action_required) && !item?.resolved_at;
}

/**
 * @param {import('./types').OpsNotification} item
 */
export function isReminderEligible(item) {
    return isActionable(item) && REMINDER_ELIGIBLE_TYPES.has(item.type);
}

/**
 * @param {import('./types').OpsNotification[]} items
 */
export function sortActionRequired(items) {
    const priorityRank = { critical: 0, high: 1, normal: 2, low: 3 };

    return [...items].sort((a, b) => {
        const pa = priorityRank[a.priority] ?? 2;
        const pb = priorityRank[b.priority] ?? 2;
        if (pa !== pb) {
            return pa - pb;
        }

        const ta = a.created_at ? Date.parse(a.created_at) : 0;
        const tb = b.created_at ? Date.parse(b.created_at) : 0;

        return ta - tb;
    });
}

/**
 * @param {string|null|undefined} iso
 */
export function formatElapsed(iso) {
    if (!iso) {
        return '';
    }

    const ms = Date.now() - Date.parse(iso);
    if (!Number.isFinite(ms) || ms < 0) {
        return '';
    }

    const seconds = Math.floor(ms / 1000);
    if (seconds < 60) {
        return `${seconds}s waiting`;
    }

    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) {
        return `${minutes}m waiting`;
    }

    const hours = Math.floor(minutes / 60);

    return `${hours}h waiting`;
}

/**
 * @param {string|null|undefined} iso
 */
export function formatWhen(iso) {
    if (!iso) {
        return '';
    }

    try {
        return new Intl.DateTimeFormat(undefined, {
            dateStyle: 'short',
            timeStyle: 'short',
        }).format(new Date(iso));
    } catch {
        return iso;
    }
}
