import { useEffect, useState } from 'react';
import { useAuthStore } from '../stores/authStore';
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
      realtimeConnection.disconnect();
      return;
    }

    const roleChannel = isWaiter(customer) ? 'role.waiter' : null;
    void realtimeConnection.connect(customer.id, roleChannel);

    return () => {
      realtimeConnection.disconnect();
    };
  }, [status, customer?.id, customer?.role]);

  return connectionState;
}
