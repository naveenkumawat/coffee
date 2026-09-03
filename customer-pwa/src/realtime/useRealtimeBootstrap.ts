import { useEffect, useState } from 'react';
import { useAuthStore } from '../stores/authStore';
import { useNotificationStore } from '../stores/notificationStore';
import { isWaiter } from '../utils/roles';
import { realtimeConnection } from './RealtimeConnection';
import { RealtimeConnectionState } from './types';

/**
 * Connects the shared Echo client when authenticated; disconnects on logout.
 * Safe under React StrictMode (connect is generation-guarded; effect cleanup disconnects).
 */
export function useRealtimeBootstrap(): RealtimeConnectionState {
  const status = useAuthStore((state) => state.status);
  const customer = useAuthStore((state) => state.customer);
  const [connectionState, setConnectionState] = useState<RealtimeConnectionState>(
    () => realtimeConnection.getState(),
  );

  useEffect(() => realtimeConnection.subscribe(setConnectionState), []);

  useEffect(() => {
    if (status !== 'authenticated' || !customer?.id) {
      useNotificationStore.getState().reset();
      realtimeConnection.disconnect();
      return;
    }

    const roleChannel = isWaiter(customer) ? 'role.waiter' : null;
    void realtimeConnection.connect(customer.id, roleChannel);

    const unsubscribe = realtimeConnection.onOperationalNotification((payload) => {
      const recipientId = Number(payload.recipient_id);
      if (!Number.isFinite(recipientId) || recipientId <= 0) {
        return;
      }

      useNotificationStore.getState().upsertFromRealtime({
        recipient_id: recipientId,
        id: Number(payload.id) || undefined,
        uuid: typeof payload.uuid === 'string' ? payload.uuid : undefined,
        type: typeof payload.type === 'string' ? payload.type : undefined,
        category: typeof payload.category === 'string' ? payload.category : undefined,
        priority: typeof payload.priority === 'string' ? payload.priority : undefined,
        title: typeof payload.title === 'string' ? payload.title : undefined,
        message: typeof payload.message === 'string' ? payload.message : undefined,
        action_required: Boolean(payload.action_required),
        action_code: typeof payload.action_code === 'string' ? payload.action_code : null,
        action_url: typeof payload.action_url === 'string' ? payload.action_url : null,
        created_at: typeof payload.created_at === 'string' ? payload.created_at : null,
      });

      void useNotificationStore.getState().markDelivered(recipientId).catch(() => {
        // Delivery ACK is best-effort; REST list remains authoritative.
      });
    });

    return () => {
      unsubscribe();
      realtimeConnection.disconnect();
    };
  }, [status, customer?.id, customer?.role]);

  return connectionState;
}
