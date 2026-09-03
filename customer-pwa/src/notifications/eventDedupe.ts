const MAX_ENTRIES = 200;
const SESSION_KEY = 'coffee.realtime.event-dedupe';

function loadSessionIds(): string[] {
  try {
    const raw = sessionStorage.getItem(SESSION_KEY);
    const parsed = raw ? JSON.parse(raw) : [];
    return Array.isArray(parsed) ? parsed.map(String) : [];
  } catch {
    return [];
  }
}

function saveSessionIds(ids: string[]): void {
  try {
    sessionStorage.setItem(SESSION_KEY, JSON.stringify(ids.slice(-MAX_ENTRIES)));
  } catch {
    // ignore
  }
}

export function createEventDedupe(prefix = 'evt'): { claim: (id: string | number | null | undefined) => boolean } {
  const seen = new Set(loadSessionIds().filter((id) => id.startsWith(`${prefix}:`)));

  function remember(id: string): boolean {
    const key = `${prefix}:${id}`;
    if (seen.has(key)) {
      return false;
    }

    seen.add(key);
    if (seen.size > MAX_ENTRIES) {
      const first = seen.values().next().value as string | undefined;
      if (first) {
        seen.delete(first);
      }
    }

    saveSessionIds([...seen]);

    return true;
  }

  return {
    claim(id) {
      if (id === null || id === undefined || id === '') {
        return true;
      }

      return remember(String(id));
    },
  };
}

export function createSyncCoalescer(run: () => void | Promise<void>, waitMs = 400): { request: () => void } {
  let timer: number | null = null;
  let inflight: Promise<void> | null = null;
  let pending = false;

  async function flush(): Promise<void> {
    timer = null;
    if (inflight) {
      pending = true;

      return;
    }

    inflight = Promise.resolve()
      .then(() => run())
      .catch(() => undefined)
      .then(() => {
        inflight = null;
        if (pending) {
          pending = false;
          schedule();
        }
      });
  }

  function schedule(): void {
    if (timer !== null) {
      return;
    }
    timer = window.setTimeout(() => {
      void flush();
    }, waitMs);
  }

  return {
    request() {
      schedule();
    },
  };
}
