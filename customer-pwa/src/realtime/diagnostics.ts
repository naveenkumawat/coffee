/**
 * Minimal client realtime diagnostics for production troubleshooting (R1.7).
 * Never stores notification/order payloads or secrets.
 */

export type RealtimeDiagnosticsSnapshot = {
  source: string;
  connection_state: string;
  reconnect_attempts: number;
  last_connected_at: string | null;
  last_disconnected_at: string | null;
  last_event_at: string | null;
  last_event_kind: string | null;
  last_reconcile_at: string | null;
  presence_heartbeat_at: string | null;
  presence_online: boolean;
};

export function createRealtimeDiagnostics(source = 'pwa'): {
  snapshot: () => RealtimeDiagnosticsSnapshot;
  setConnectionState: (next: string) => void;
  markEvent: (kind?: string) => void;
  markReconcile: () => void;
  markPresenceHeartbeat: () => void;
} {
  const state: RealtimeDiagnosticsSnapshot = {
    source,
    connection_state: 'idle',
    reconnect_attempts: 0,
    last_connected_at: null,
    last_disconnected_at: null,
    last_event_at: null,
    last_event_kind: null,
    last_reconcile_at: null,
    presence_heartbeat_at: null,
    presence_online: false,
  };

  return {
    snapshot() {
      return { ...state };
    },
    setConnectionState(next) {
      const previous = state.connection_state;
      state.connection_state = String(next || 'idle');

      if (state.connection_state === 'connected' || state.connection_state === 'reconnected') {
        state.last_connected_at = new Date().toISOString();
        state.presence_online = true;
        if (previous === 'reconnecting' || previous === 'disconnected' || previous === 'failed') {
          state.reconnect_attempts += 1;
        }
      }

      if (
        state.connection_state === 'disconnected'
        || state.connection_state === 'failed'
        || state.connection_state === 'reconnecting'
      ) {
        state.last_disconnected_at = new Date().toISOString();
        if (state.connection_state !== 'reconnecting') {
          state.presence_online = false;
        }
      }
    },
    markEvent(kind) {
      state.last_event_at = new Date().toISOString();
      state.last_event_kind = kind || 'event';
    },
    markReconcile() {
      state.last_reconcile_at = new Date().toISOString();
    },
    markPresenceHeartbeat() {
      state.presence_heartbeat_at = new Date().toISOString();
      state.presence_online = true;
    },
  };
}

export const realtimeDiagnostics = createRealtimeDiagnostics('pwa');

declare global {
  interface Window {
    __COFFEE_REALTIME_DIAGNOSTICS__?: RealtimeDiagnosticsSnapshot;
  }
}

export function publishRealtimeDiagnostics(): void {
  window.__COFFEE_REALTIME_DIAGNOSTICS__ = realtimeDiagnostics.snapshot();
}
