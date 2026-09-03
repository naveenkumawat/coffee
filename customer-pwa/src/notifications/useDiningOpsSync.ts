import { useEffect, useRef } from 'react';
import { useNotificationStore } from '../stores/notificationStore';
import { realtimeConnection } from '../realtime/RealtimeConnection';
import { SYNC_ABSENCE_MS, SYNC_COALESCE_MS } from './config';
import { createEventDedupe, createSyncCoalescer } from './eventDedupe';
import {
  DiningOpsPayload,
  normalizeDiningOpsPayload,
  subscribeDiningOps,
} from './diningOpsSignals';

type Filter = (payload: DiningOpsPayload) => boolean;

/**
 * Soft-reconcile dining UI from `.dining.ops` signals + reconnect/online/visibility.
 * Optional private `dining-session.{id}` / `table.{id}` subscriptions for scoped clients.
 */
export function useDiningOpsSync(
  reload: () => void | Promise<void>,
  filter?: Filter,
  options?: {
    sessionId?: number | string | null;
    tableId?: number | string | null;
  },
): void {
  const connectionState = useNotificationStore((state) => state.connectionState);
  const previousConnection = useRef(connectionState);
  const hiddenSince = useRef<number | null>(null);
  const reloadRef = useRef(reload);
  const filterRef = useRef(filter);
  const dedupeRef = useRef(createEventDedupe('dining-page'));
  const coalescerRef = useRef(createSyncCoalescer(() => reloadRef.current(), SYNC_COALESCE_MS));

  reloadRef.current = reload;
  filterRef.current = filter;

  const handlePayload = (raw: Record<string, unknown> | DiningOpsPayload): void => {
    const normalized = normalizeDiningOpsPayload(raw as Record<string, unknown>);
    if (!normalized) {
      return;
    }

    if (normalized.event_id && !dedupeRef.current.claim(normalized.event_id)) {
      return;
    }

    if (filterRef.current && !filterRef.current(normalized)) {
      return;
    }

    coalescerRef.current.request();
  };

  useEffect(() => {
    coalescerRef.current = createSyncCoalescer(() => reloadRef.current(), SYNC_COALESCE_MS);
    dedupeRef.current = createEventDedupe('dining-page');

    return subscribeDiningOps((payload) => {
      handlePayload(payload);
    });
  }, []);

  useEffect(() => {
    const sessionId = options?.sessionId;
    if (sessionId === null || sessionId === undefined || sessionId === '') {
      return;
    }

    return realtimeConnection.subscribeDiningSession(sessionId, (payload) => {
      handlePayload(payload);
    });
  }, [options?.sessionId]);

  useEffect(() => {
    const tableId = options?.tableId;
    if (tableId === null || tableId === undefined || tableId === '') {
      return;
    }

    return realtimeConnection.subscribeTable(tableId, (payload) => {
      handlePayload(payload);
    });
  }, [options?.tableId]);

  useEffect(() => {
    const previous = previousConnection.current;
    previousConnection.current = connectionState;

    if (
      (connectionState === 'connected' || connectionState === 'reconnected') &&
      previous !== connectionState &&
      previous !== 'idle'
    ) {
      coalescerRef.current.request();
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
        coalescerRef.current.request();
      }
    };

    const onOnline = (): void => {
      coalescerRef.current.request();
    };

    document.addEventListener('visibilitychange', onVisibility);
    window.addEventListener('online', onOnline);

    return () => {
      document.removeEventListener('visibilitychange', onVisibility);
      window.removeEventListener('online', onOnline);
    };
  }, []);
}
