import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { getApiBaseUrl } from '../api/client';
import {
  RealtimeConnectionListener,
  RealtimeConnectionState,
  RealtimeProbePayload,
} from './types';
import { DiningOpsPayload } from '../notifications/diningOpsSignals';

type EchoInstance = InstanceType<typeof Echo>;

function readEnvConfig(): {
  enabled: boolean;
  key: string;
  host: string;
  port: number;
  scheme: 'http' | 'https';
} {
  const key = (import.meta.env.VITE_REVERB_APP_KEY ?? '').trim();
  const flag = (import.meta.env.VITE_REALTIME_ENABLED ?? 'true').toLowerCase();
  const enabled = Boolean(key) && flag !== 'false' && flag !== '0';

  return {
    enabled,
    key,
    host: (import.meta.env.VITE_REVERB_HOST ?? window.location.hostname).trim() || 'localhost',
    port: Number(import.meta.env.VITE_REVERB_PORT ?? 8080) || 8080,
    scheme: (import.meta.env.VITE_REVERB_SCHEME ?? 'http').trim() === 'https' ? 'https' : 'http',
  };
}

function broadcastingAuthUrl(): string {
  const apiBase = getApiBaseUrl();
  try {
    const url = new URL(apiBase, window.location.origin);
    // /coffee/api/v1 → /coffee/broadcasting/auth (or /api/v1 → /broadcasting/auth)
    url.pathname = url.pathname.replace(/\/api\/v1\/?$/, '/broadcasting/auth');
    if (!url.pathname.endsWith('/broadcasting/auth')) {
      url.pathname = '/broadcasting/auth';
    }
    return url.toString();
  } catch {
    return '/broadcasting/auth';
  }
}

function readCsrfToken(): string | null {
  const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
  if (match?.[1]) {
    return decodeURIComponent(match[1]);
  }
  return null;
}

/**
 * Singleton realtime client for the customer/waiter PWA.
 * Lazy — never connects at module import time. StrictMode-safe connect/disconnect.
 */
class RealtimeConnectionService {
  private echo: EchoInstance | null = null;
  private state: RealtimeConnectionState = 'idle';
  private listeners = new Set<RealtimeConnectionListener>();
  private connectGeneration = 0;
  private activeUserId: number | null = null;
  private boundHandlers: Array<[string, (...args: unknown[]) => void]> = [];
  private probeHandlers = new Set<(payload: RealtimeProbePayload) => void>();
  private notificationHandlers = new Set<(payload: Record<string, unknown>) => void>();
  private diningOpsHandlers = new Set<(payload: Record<string, unknown>) => void>();
  private scopedChannelRefCounts = new Map<string, number>();

  getState(): RealtimeConnectionState {
    return this.state;
  }

  subscribe(listener: RealtimeConnectionListener): () => void {
    this.listeners.add(listener);
    listener(this.state);
    return () => {
      this.listeners.delete(listener);
    };
  }

  onProbe(handler: (payload: RealtimeProbePayload) => void): () => void {
    this.probeHandlers.add(handler);
    return () => {
      this.probeHandlers.delete(handler);
    };
  }

  onOperationalNotification(handler: (payload: Record<string, unknown>) => void): () => void {
    this.notificationHandlers.add(handler);
    return () => {
      this.notificationHandlers.delete(handler);
    };
  }

  onDiningOps(handler: (payload: Record<string, unknown>) => void): () => void {
    this.diningOpsHandlers.add(handler);
    return () => {
      this.diningOpsHandlers.delete(handler);
    };
  }

  subscribeDiningSession(
    sessionId: number | string,
    handler: (payload: DiningOpsPayload) => void,
  ): () => void {
    return this.subscribeScopedChannel(`dining-session.${sessionId}`, handler);
  }

  subscribeTable(
    tableId: number | string,
    handler: (payload: DiningOpsPayload) => void,
  ): () => void {
    return this.subscribeScopedChannel(`table.${tableId}`, handler);
  }

  private subscribeScopedChannel(
    channelName: string,
    handler: (payload: DiningOpsPayload) => void,
  ): () => void {
    const echo = this.echo;
    if (!echo) {
      return () => undefined;
    }

    const refs = this.scopedChannelRefCounts.get(channelName) ?? 0;
    this.scopedChannelRefCounts.set(channelName, refs + 1);

    const channel = echo.private(channelName);
    const listener = (payload: Record<string, unknown>): void => {
      handler(payload as DiningOpsPayload);
    };
    channel.listen('.dining.ops', listener);

    return () => {
      try {
        channel.stopListening('.dining.ops', listener);
      } catch {
        // ignore
      }

      const next = (this.scopedChannelRefCounts.get(channelName) ?? 1) - 1;
      if (next <= 0) {
        this.scopedChannelRefCounts.delete(channelName);
        try {
          echo.leave(channelName);
        } catch {
          // ignore
        }
      } else {
        this.scopedChannelRefCounts.set(channelName, next);
      }
    };
  }

  private setState(next: RealtimeConnectionState): void {
    if (this.state === next) {
      return;
    }
    this.state = next;
    this.listeners.forEach((listener) => listener(next));
  }

  async connect(
    userId: number,
    roleChannel: string | null = null,
    options: { joinPresence?: boolean } = {},
  ): Promise<void> {
    const env = readEnvConfig();

    if (!env.enabled || !env.key) {
      this.setState('offline');
      return;
    }

    if (this.echo && this.activeUserId === userId) {
      return;
    }

    this.disconnect();

    const generation = ++this.connectGeneration;
    this.activeUserId = userId;
    this.setState('connecting');

    window.Pusher = Pusher;

    const echo = new Echo({
      broadcaster: 'reverb',
      key: env.key,
      wsHost: env.host,
      wsPort: env.port,
      wssPort: env.port,
      forceTLS: env.scheme === 'https',
      enabledTransports: ['ws', 'wss'],
      authEndpoint: broadcastingAuthUrl(),
      auth: {
        headers: {
          Accept: 'application/json',
          'X-XSRF-TOKEN': readCsrfToken() ?? '',
          'X-Requested-With': 'XMLHttpRequest',
        },
      },
      withCredentials: true,
    });

    if (generation !== this.connectGeneration) {
      echo.disconnect();
      return;
    }

    this.echo = echo;
    this.bindConnectionEvents(echo, generation);

    echo
      .private(`user.${userId}`)
      .listen('.realtime.probe', (payload: RealtimeProbePayload) => {
        this.probeHandlers.forEach((handler) => handler(payload));
      })
      .listen('.operational.notification', (payload: Record<string, unknown>) => {
        this.notificationHandlers.forEach((handler) => handler(payload));
      });

    if (roleChannel) {
      echo
        .private(roleChannel)
        .listen('.dining.ops', (payload: Record<string, unknown>) => {
          this.diningOpsHandlers.forEach((handler) => handler(payload));
        });
    }

    if (options.joinPresence) {
      echo.join('ops');
    }
  }

  private bindConnectionEvents(echo: EchoInstance, generation: number): void {
    const connector = echo.connector as { pusher?: { connection: {
      bind: (event: string, handler: (...args: unknown[]) => void) => void;
      unbind: (event: string, handler: (...args: unknown[]) => void) => void;
    } } } | undefined;
    const pusher = connector?.pusher;
    if (!pusher?.connection) {
      this.setState('failed');
      return;
    }

    const bind = (event: string, handler: (...args: unknown[]) => void): void => {
      pusher.connection.bind(event, handler);
      this.boundHandlers.push([event, handler]);
    };

    bind('connected', () => {
      if (generation !== this.connectGeneration) {
        return;
      }
      const next = this.state === 'reconnecting' ? 'reconnected' : 'connected';
      this.setState(next);
      if (next === 'reconnected') {
        window.setTimeout(() => {
          if (generation === this.connectGeneration && this.state === 'reconnected') {
            this.setState('connected');
          }
        }, 1200);
      }
    });

    bind('connecting', () => {
      if (generation !== this.connectGeneration) {
        return;
      }
      if (this.state === 'connected' || this.state === 'reconnected') {
        this.setState('reconnecting');
      }
    });

    bind('unavailable', () => {
      if (generation === this.connectGeneration) {
        this.setState('failed');
      }
    });

    bind('failed', () => {
      if (generation === this.connectGeneration) {
        this.setState('failed');
      }
    });

    bind('disconnected', () => {
      if (generation === this.connectGeneration) {
        this.setState('disconnected');
      }
    });
  }

  disconnect(): void {
    this.connectGeneration += 1;
    this.activeUserId = null;
    this.scopedChannelRefCounts.clear();

    if (this.echo) {
      const connector = this.echo.connector as { pusher?: { connection: {
        unbind: (event: string, handler: (...args: unknown[]) => void) => void;
      } } } | undefined;
      const pusher = connector?.pusher;
      if (pusher?.connection) {
        this.boundHandlers.forEach(([event, handler]) => {
          try {
            pusher.connection.unbind(event, handler);
          } catch {
            // Ignore unbind races during teardown.
          }
        });
      }
      this.boundHandlers = [];
      try {
        this.echo.disconnect();
      } catch {
        // Ignore disconnect races.
      }
      this.echo = null;
    }

    if (this.state !== 'offline' && this.state !== 'idle') {
      this.setState('disconnected');
    }
  }
}

export const realtimeConnection = new RealtimeConnectionService();

declare global {
  interface Window {
    Pusher: typeof Pusher;
  }
}
