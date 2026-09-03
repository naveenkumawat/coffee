/**
 * Bounded in-memory/session dedupe for realtime event / recipient ids (R1.5).
 * Prevents duplicate toast/sound/upsert storms when the same socket event arrives twice.
 */

const MAX_ENTRIES = 200;
const SESSION_KEY = 'coffee.realtime.event-dedupe';

function loadSessionIds() {
    try {
        const raw = sessionStorage.getItem(SESSION_KEY);
        const parsed = raw ? JSON.parse(raw) : [];
        return Array.isArray(parsed) ? parsed.map(String) : [];
    } catch {
        return [];
    }
}

function saveSessionIds(ids) {
    try {
        sessionStorage.setItem(SESSION_KEY, JSON.stringify(ids.slice(-MAX_ENTRIES)));
    } catch {
        // ignore quota
    }
}

export function createEventDedupe(prefix = 'evt') {
    const seen = new Set(loadSessionIds().filter((id) => id.startsWith(`${prefix}:`)));

    function remember(id) {
        const key = `${prefix}:${id}`;
        if (seen.has(key)) {
            return false;
        }

        seen.add(key);
        if (seen.size > MAX_ENTRIES) {
            const first = seen.values().next().value;
            seen.delete(first);
        }

        saveSessionIds([...seen]);

        return true;
    }

    /**
     * @param {string|number|null|undefined} id
     * @returns {boolean} true when this is the first time seeing the id
     */
    function claim(id) {
        if (id === null || id === undefined || id === '') {
            return true;
        }

        return remember(String(id));
    }

    return { claim };
}

/**
 * Coalesce rapid sync triggers into a single REST reconciliation.
 */
export function createSyncCoalescer(run, waitMs = 400) {
    let timer = null;
    let inflight = null;
    let pending = false;

    async function flush() {
        timer = null;
        if (inflight) {
            pending = true;

            return;
        }

        inflight = Promise.resolve()
            .then(() => run())
            .catch(() => undefined)
            .finally(() => {
                inflight = null;
                if (pending) {
                    pending = false;
                    schedule();
                }
            });
    }

    function schedule() {
        if (timer !== null) {
            return;
        }
        timer = window.setTimeout(() => {
            void flush();
        }, waitMs);
    }

    function request() {
        schedule();
    }

    return { request };
}
