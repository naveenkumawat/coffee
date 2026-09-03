import { useEffect, useRef, useState } from 'react';
import { useAuthStore } from '../stores/authStore';
import { useNotificationStore } from '../stores/notificationStore';
import { isWaiter } from '../utils/roles';
import { realtimeConnection } from './RealtimeConnection';
import { RealtimeConnectionState } from './types';
import { normalizeRealtimePayload } from '../notifications/normalize';
import { useActionReminderEngine, presentImmediateAlert } from '../notifications/useActionReminderEngine';
import { createTabLeader } from '../notifications/tabLeader';
import { createNotificationSoundManager } from '../notifications/sound';
import { SYNC_ABSENCE_MS } from '../notifications/config';

/**
 * Connects Echo, syncs operational notifications, and runs reminder engine for staff PWA.
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
  const staffSession = status === 'authenticated' && Boolean(customer?.id);
  const reminderEnabled = staffSession && isWaiter(customer);

  useActionReminderEngine(reminderEnabled);

  useEffect(() => realtimeConnection.subscribe(setConnectionState), []);

  useEffect(() => {
    if (!staffSession || !customer?.id) {
      useNotificationStore.getState().reset();
      realtimeConnection.disconnect();
      return;
    }

    leaderRef.current = createTabLeader();
    soundRef.current = createNotificationSoundManager();

    const roleChannel = isWaiter(customer) ? 'role.waiter' : null;
    void realtimeConnection.connect(customer.id, roleChannel);
    void useNotificationStore.getState().sync();

    const unsubscribe = realtimeConnection.onOperationalNotification((payload) => {
      const normalized = normalizeRealtimePayload(payload);
      if (!normalized) {
        return;
      }

      useNotificationStore.getState().upsertFromRealtime(normalized);
      void useNotificationStore.getState().markDelivered(normalized.recipient_id).catch(() => undefined);

      if (leaderRef.current?.isLeader()) {
        const item = useNotificationStore.getState().items.find(
          (row) => row.recipient_id === normalized.recipient_id,
        );
        if (item) {
          presentImmediateAlert(item, true);
          void soundRef.current?.play();
        }
      }
    });

    const onVisibility = (): void => {
      if (document.hidden) {
        hiddenSince.current = Date.now();
        return;
      }

      const away = hiddenSince.current ? Date.now() - hiddenSince.current : 0;
      hiddenSince.current = null;
      if (away >= SYNC_ABSENCE_MS) {
        void useNotificationStore.getState().sync();
      }
    };

    const onOnline = (): void => {
      void useNotificationStore.getState().sync();
    };

    document.addEventListener('visibilitychange', onVisibility);
    window.addEventListener('online', onOnline);

    return () => {
      unsubscribe();
      document.removeEventListener('visibilitychange', onVisibility);
      window.removeEventListener('online', onOnline);
      leaderRef.current?.destroy();
      realtimeConnection.disconnect();
    };
  }, [staffSession, customer?.id, customer?.role]);

  useEffect(() => {
    useNotificationStore.getState().setConnectionState(connectionState);
    if (connectionState === 'connected' || connectionState === 'reconnected') {
      void useNotificationStore.getState().sync();
    }
  }, [connectionState]);

  return connectionState;
}
