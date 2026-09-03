import { useEffect, useRef } from 'react';
import { useNotificationStore } from '../stores/notificationStore';
import { SYNC_ABSENCE_MS } from './config';
import { LiveCanonicalSignal, subscribeLiveSignals } from './liveSignals';

type Filter = (signal: LiveCanonicalSignal) => boolean;

/**
 * Refetch canonical REST state when a matching customer notification arrives,
 * or after reconnect / online / visibility resume.
 */
export function useLiveCanonicalSync(
  reload: () => void | Promise<void>,
  filter?: Filter,
): void {
  const connectionState = useNotificationStore((state) => state.connectionState);
  const previousConnection = useRef(connectionState);
  const hiddenSince = useRef<number | null>(null);
  const reloadRef = useRef(reload);
  const filterRef = useRef(filter);

  reloadRef.current = reload;
  filterRef.current = filter;

  useEffect(() => {
    return subscribeLiveSignals((signal) => {
      if (filterRef.current && !filterRef.current(signal)) {
        return;
      }

      void reloadRef.current();
    });
  }, []);

  useEffect(() => {
    const previous = previousConnection.current;
    previousConnection.current = connectionState;

    if (
      (connectionState === 'connected' || connectionState === 'reconnected') &&
      previous !== connectionState &&
      previous !== 'idle'
    ) {
      void reloadRef.current();
    }
  }, [connectionState]);

  useEffect(() => {
    const onVisibility = (): void => {
      if (document.hidden) {
        hiddenSince.current = Date.now();

        return;
      }

      const away = hiddenSince.current ? Date.now() - hiddenSince.current : 0;
      hiddenSince.current = null;
      if (away >= SYNC_ABSENCE_MS) {
        void reloadRef.current();
      }
    };

    const onOnline = (): void => {
      void reloadRef.current();
    };

    document.addEventListener('visibilitychange', onVisibility);
    window.addEventListener('online', onOnline);

    return () => {
      document.removeEventListener('visibilitychange', onVisibility);
      window.removeEventListener('online', onOnline);
    };
  }, []);
}
