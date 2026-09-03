import { CHANNEL_NAME, LEADER_HEARTBEAT_MS, LEADER_STALE_MS, STORAGE_LEADER_KEY } from './config';

type LeadershipListener = (isLeader: boolean) => void;

export function createTabLeader(onChange?: LeadershipListener) {
  const tabId = `${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
  let isLeader = false;
  let heartbeatTimer: number | null = null;
  let channel: BroadcastChannel | null = null;

  function readLeader(): { tabId: string; at: number } | null {
    try {
      const raw = localStorage.getItem(STORAGE_LEADER_KEY);
      return raw ? (JSON.parse(raw) as { tabId: string; at: number }) : null;
    } catch {
      return null;
    }
  }

  function writeLeader(): void {
    try {
      localStorage.setItem(STORAGE_LEADER_KEY, JSON.stringify({ tabId, at: Date.now() }));
    } catch {
      // ignore
    }
  }

  function setLeader(next: boolean): void {
    if (isLeader === next) {
      return;
    }
    isLeader = next;
    onChange?.(isLeader);
  }

  function claimIfNeeded(): void {
    const current = readLeader();
    const now = Date.now();
    if (!current || current.tabId === tabId || now - Number(current.at || 0) > LEADER_STALE_MS) {
      writeLeader();
      setLeader(true);
      return;
    }
    setLeader(false);
  }

  if (typeof BroadcastChannel !== 'undefined') {
    channel = new BroadcastChannel(CHANNEL_NAME);
    channel.onmessage = (event: MessageEvent<{ type?: string; tabId?: string }>) => {
      if (event.data?.tabId === tabId) {
        return;
      }
      if (event.data?.type === 'leader-resign') {
        claimIfNeeded();
      }
      if (event.data?.type === 'leader-heartbeat' && event.data.tabId !== tabId) {
        setLeader(false);
      }
    };
  }

  window.addEventListener('storage', (event) => {
    if (event.key === STORAGE_LEADER_KEY) {
      claimIfNeeded();
    }
  });

  claimIfNeeded();
  heartbeatTimer = window.setInterval(() => {
    if (isLeader) {
      writeLeader();
      channel?.postMessage({ type: 'leader-heartbeat', tabId });
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
      channel?.postMessage({ type: 'leader-resign', tabId });
    }
  });

  return {
    isLeader: () => isLeader,
    destroy: () => {
      if (heartbeatTimer) {
        window.clearInterval(heartbeatTimer);
      }
      channel?.close();
    },
  };
}
