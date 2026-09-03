/**
 * Minimal client realtime diagnostics for production troubleshooting (R1.7).
 * Never stores notification/order payloads or secrets.
 */

function createRealtimeDiagnostics(source = 'blade') {
    const state = {
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

    function snapshot() {
        return { ...state };
    }

    function setConnectionState(next) {
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
    }

    function markEvent(kind) {
        state.last_event_at = new Date().toISOString();
        state.last_event_kind = kind || 'event';
    }

    function markReconcile() {
        state.last_reconcile_at = new Date().toISOString();
    }

    function markPresenceHeartbeat() {
        state.presence_heartbeat_at = new Date().toISOString();
        state.presence_online = true;
    }

    return {
        snapshot,
        setConnectionState,
        markEvent,
        markReconcile,
        markPresenceHeartbeat,
    };
}

export { createRealtimeDiagnostics };
