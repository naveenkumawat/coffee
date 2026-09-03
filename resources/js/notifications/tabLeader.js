import { CHANNEL_NAME, LEADER_HEARTBEAT_MS, LEADER_STALE_MS, STORAGE_EVENT_KEY, STORAGE_LEADER_KEY } from './config';

/**
 * Elects one browser tab as leader for sound + reminder presentation.
 * Uses BroadcastChannel when available, otherwise localStorage heartbeat.
 */
export function createTabLeader(onLeadershipChange) {
    const tabId = `${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
    let isLeader = false;
    let heartbeatTimer = null;
    /** @type {BroadcastChannel|null} */
    let channel = null;

    function readLeader() {
        try {
            const raw = localStorage.getItem(STORAGE_LEADER_KEY);
            return raw ? JSON.parse(raw) : null;
        } catch {
            return null;
        }
    }

    function writeLeader() {
        try {
            localStorage.setItem(STORAGE_LEADER_KEY, JSON.stringify({
                tabId,
                at: Date.now(),
            }));
        } catch {
            // Private mode / quota — become local-only leader.
        }
    }

    function setLeader(next) {
        if (isLeader === next) {
            return;
        }

        isLeader = next;
        onLeadershipChange?.(isLeader);
    }

    function claimIfNeeded() {
        const current = readLeader();
        const now = Date.now();

        if (!current || current.tabId === tabId || (now - Number(current.at || 0)) > LEADER_STALE_MS) {
            writeLeader();
            setLeader(true);

            return;
        }

        setLeader(false);
    }

    function broadcast(type, detail = {}) {
        const message = { type, tabId, ...detail };

        if (channel) {
            try {
                channel.postMessage(message);
            } catch {
                // ignore
            }
        }

        try {
            localStorage.setItem(STORAGE_EVENT_KEY, JSON.stringify({ ...message, at: Date.now() }));
            localStorage.removeItem(STORAGE_EVENT_KEY);
        } catch {
            // ignore
        }
    }

    function onMessage(data) {
        if (!data || data.tabId === tabId) {
            return;
        }

        if (data.type === 'leader-heartbeat') {
            const current = readLeader();
            if (current?.tabId && current.tabId !== tabId) {
                setLeader(false);
            }
        }

        if (data.type === 'leader-resign') {
            claimIfNeeded();
        }
    }

    if (typeof BroadcastChannel !== 'undefined') {
        channel = new BroadcastChannel(CHANNEL_NAME);
        channel.onmessage = (event) => onMessage(event.data);
    }

    window.addEventListener('storage', (event) => {
        if (event.key === STORAGE_LEADER_KEY) {
            claimIfNeeded();
        }

        if (event.key === STORAGE_EVENT_KEY && event.newValue) {
            try {
                onMessage(JSON.parse(event.newValue));
            } catch {
                // ignore
            }
        }
    });

    claimIfNeeded();
    heartbeatTimer = window.setInterval(() => {
        if (isLeader) {
            writeLeader();
            broadcast('leader-heartbeat');
        } else {
            claimIfNeeded();
        }
    }, LEADER_HEARTBEAT_MS);

    window.addEventListener('beforeunload', () => {
        if (isLeader) {
            try {
                localStorage.removeItem(STORAGE_LEADER_KEY);
            } catch {
                // ignore
            }
            broadcast('leader-resign');
        }
    });

    return {
        get tabId() {
            return tabId;
        },
        isLeader() {
            return isLeader;
        },
        broadcast,
        destroy() {
            if (heartbeatTimer) {
                window.clearInterval(heartbeatTimer);
            }
            channel?.close();
        },
    };
}
