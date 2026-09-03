import { useEffect, useRef, useState } from 'react';
import { useAuthStore } from '../stores/authStore';
import { useNotificationStore } from '../stores/notificationStore';
import { isWaiter } from '../utils/roles';
import { realtimeConnection } from './RealtimeConnection';
import { RealtimeConnectionState } from './types';
import { normalizeRealtimePayload } from '../notifications/normalize';
import {
  presentImmediateAlert,
  shouldPlayAlertSound,
  useActionReminderEngine,
} from '../notifications/useActionReminderEngine';
import { createTabLeader } from '../notifications/tabLeader';
import { createNotificationSoundManager } from '../notifications/sound';
import { SYNC_ABSENCE_MS, SYNC_COALESCE_MS } from '../notifications/config';
import { emitLiveSignal, toLiveSignal } from '../notifications/liveSignals';
import { createEventDedupe, createSyncCoalescer } from '../notifications/eventDedupe';
import { emitDiningOps, normalizeDiningOpsPayload } from '../notifications/diningOpsSignals';
import { post } from '../api/client';
import { publishRealtimeDiagnostics, realtimeDiagnostics } from './diagnostics';

/**
 * Connects Echo, syncs operational notifications, reminders (waiter), and customer live signals.
 */
export function useRealtimeBootstrap(): RealtimeConnectionState {
  const status = useAuthStore((state) => state.status);
  const customer = useAuthStore((state) => state.customer);
  const [connectionState, setConnectionState] = useState<RealtimeConnectionState>(
    () => realtimeConnection.getState(),
  );
  const leaderRef = useRef<ReturnType<typeof createTabLeader> | null>(null);
  const soundRef = useRef<ReturnType<typeof createNotificationSoundManager> | null>(null);
  const hiddenSince = useRef<number | null>(null);
  const dedupeRef = useRef(createEventDedupe('ops'));
  const diningDedupeRef = useRef(createEventDedupe('dining'));
  const syncCoalescerRef = useRef(
    createSyncCoalescer(async () => {
      await useNotificationStore.getState().sync();
      realtimeDiagnostics.markReconcile();
      publishRealtimeDiagnostics();
    }, SYNC_COALESCE_MS),
  );
  const authenticated = status === 'authenticated' && Boolean(customer?.id);
  const reminderEnabled = authenticated && isWaiter(customer);

  useActionReminderEngine(reminderEnabled);

  useEffect(() => realtimeConnection.subscribe((next) => {
    setConnectionState(next);
    realtimeDiagnostics.setConnectionState(next);
    publishRealtimeDiagnostics();
  }), []);

  useEffect(() => {
    if (!authenticated || !customer?.id) {
      useNotificationStore.getState().reset();
      realtimeConnection.disconnect();
      return;
    }

    leaderRef.current = createTabLeader();
    soundRef.current = createNotificationSoundManager();
    dedupeRef.current = createEventDedupe('ops');
    diningDedupeRef.current = createEventDedupe('dining');

    const waiter = isWaiter(customer);
    const roleChannel = waiter ? 'role.waiter' : null;
    void realtimeConnection.connect(customer.id, roleChannel, { joinPresence: waiter });
    syncCoalescerRef.current.request();

    let heartbeatTimer: number | null = null;

    const presenceHeartbeat = (): void => {
      if (!waiter) {
        return;
      }
      void post('/realtime/presence/heartbeat', {})
        .then(() => {
          realtimeDiagnostics.markPresenceHeartbeat();
          publishRealtimeDiagnostics();
        })
        .catch(() => undefined);
    };

    const presenceLeave = (): void => {
      if (!waiter) {
        return;
      }
      void post('/realtime/presence/leave', {}).catch(() => undefined);
    };

    presenceHeartbeat();
    heartbeatTimer = window.setInterval(presenceHeartbeat, 20_000);

    const unsubscribe = realtimeConnection.onOperationalNotification((payload) => {
      const normalized = normalizeRealtimePayload(payload);
      if (!normalized) {
        return;
      }

      realtimeDiagnostics.markEvent('operational.notification');
      publishRealtimeDiagnostics();

      const dedupeKey = normalized.uuid
        || `${normalized.id ?? ''}:${normalized.recipient_id}:${normalized.created_at ?? ''}`;
      const isNew = dedupeRef.current.claim(dedupeKey);

      useNotificationStore.getState().upsertFromRealtime(normalized);
      void useNotificationStore.getState().markDelivered(normalized.recipient_id).catch(() => undefined);

      const item = useNotificationStore.getState().items.find(
        (row) => row.recipient_id === normalized.recipient_id,
      );

      if (item) {
        const signal = toLiveSignal(item);
        if (signal) {
          emitLiveSignal(signal);
        }
      }

      if (isNew && leaderRef.current?.isLeader() && item) {
        presentImmediateAlert(item, true);
        if (shouldPlayAlertSound(item)) {
          void soundRef.current?.play();
        }
      }
    });

    const unsubscribeDining = realtimeConnection.onDiningOps((payload) => {
      const normalized = normalizeDiningOpsPayload(payload);
      if (!normalized) {
        return;
      }

      if (normalized.event_id && !diningDedupeRef.current.claim(normalized.event_id)) {
        return;
      }

      realtimeDiagnostics.markEvent('dining.ops');
      publishRealtimeDiagnostics();
      emitDiningOps(normalized);
    });

    const onVisibility = (): void => {
      if (document.hidden) {
        hiddenSince.current = Date.now();
        return;
      }

      const away = hiddenSince.current ? Date.now() - hiddenSince.current : 0;
      hiddenSince.current = null;
      if (away >= SYNC_ABSENCE_MS) {
        syncCoalescerRef.current.request();
      }
    };

    const onOnline = (): void => {
      syncCoalescerRef.current.request();
    };

    document.addEventListener('visibilitychange', onVisibility);
    window.addEventListener('online', onOnline);

    return () => {
      unsubscribe();
      unsubscribeDining();
      document.removeEventListener('visibilitychange', onVisibility);
      window.removeEventListener('online', onOnline);
      if (heartbeatTimer) {
        window.clearInterval(heartbeatTimer);
      }
      presenceLeave();
      leaderRef.current?.destroy();
      realtimeConnection.disconnect();
    };
  }, [authenticated, customer?.id, customer?.role]);

  useEffect(() => {
    useNotificationStore.getState().setConnectionState(connectionState);
    if (connectionState === 'connected' || connectionState === 'reconnected') {
      syncCoalescerRef.current.request();
    }
  }, [connectionState]);

  return connectionState;
}
