import { REMINDER_INTERVAL_MS } from './config';
import { isReminderEligible, sortActionRequired } from './normalize';

/**
 * Centralized 30s reminder scheduler. Only the elected leader presents
 * sound/toast and POSTs /reminded.
 */
export function createActionReminderManager({
    store,
    leader,
    sound,
    presentReminder,
    recordReminder,
}) {
    /** @type {Map<number, number>} recipient_id -> nextDueAt ms */
    const schedule = new Map();
    let timer = null;

    function syncFromStore() {
        const actionable = sortActionRequired(store.getState().items.filter(isReminderEligible));
        const liveIds = new Set(actionable.map((item) => item.recipient_id));

        [...schedule.keys()].forEach((id) => {
            if (!liveIds.has(id)) {
                schedule.delete(id);
            }
        });

        actionable.forEach((item) => {
            if (!schedule.has(item.recipient_id)) {
                // Immediate alert already handled on receive; first reminder at +30s.
                schedule.set(item.recipient_id, Date.now() + REMINDER_INTERVAL_MS);
            }
        });
    }

    function armImmediate(item) {
        if (!isReminderEligible(item)) {
            return;
        }

        // Immediate presentation is separate; still schedule first repeat.
        schedule.set(item.recipient_id, Date.now() + REMINDER_INTERVAL_MS);
    }

    async function tick() {
        if (!leader.isLeader()) {
            return;
        }

        const now = Date.now();
        const due = [];

        schedule.forEach((nextDue, recipientId) => {
            if (nextDue <= now) {
                due.push(recipientId);
            }
        });

        if (due.length === 0) {
            return;
        }

        const byId = new Map(store.getState().items.map((item) => [item.recipient_id, item]));

        for (const recipientId of due) {
            const item = byId.get(recipientId);
            if (!item || !isReminderEligible(item)) {
                schedule.delete(recipientId);
                continue;
            }

            presentReminder?.(item);
            await sound?.play?.();

            try {
                const updated = await recordReminder?.(recipientId);
                if (updated) {
                    store.upsert(updated);
                }
            } catch {
                // Keep trying on next interval; server is authoritative.
            }

            schedule.set(recipientId, Date.now() + REMINDER_INTERVAL_MS);
        }
    }

    function start() {
        if (timer) {
            return;
        }

        timer = window.setInterval(() => {
            syncFromStore();
            void tick();
        }, 1000);
    }

    function stop() {
        if (timer) {
            window.clearInterval(timer);
            timer = null;
        }
        schedule.clear();
    }

    store.subscribe(() => syncFromStore());
    start();

    return {
        armImmediate,
        syncFromStore,
        stop,
        /** @internal test helper */
        _schedule: schedule,
    };
}
