import { useEffect } from 'react';
import { applyServerCacheVersion, PublicCacheSyncResult, syncPublicCacheVersion } from '../cache/version';
import { realtimeConnection } from '../realtime/RealtimeConnection';
import { useContentStore } from '../stores/contentStore';

async function syncPublicCacheAndDining(force = false): Promise<PublicCacheSyncResult> {
  const result = await syncPublicCacheVersion(force);

  if (typeof result.diningEnabled === 'boolean') {
    useContentStore.getState().applyDiningCapability(result.diningEnabled);
  }

  return result;
}

export function usePublicCacheSync(): void {
  useEffect(() => {
    void syncPublicCacheAndDining();
    void realtimeConnection.connectPublic();

    const unsubscribe = realtimeConnection.onPublicCacheInvalidated((version) => {
      void applyServerCacheVersion(version);
    });

    const syncIfVisible = (): void => {
      if (!document.hidden) {
        void syncPublicCacheAndDining();
      }
    };

    const onOnline = (): void => {
      void syncPublicCacheAndDining(true);
    };

    document.addEventListener('visibilitychange', syncIfVisible);
    window.addEventListener('online', onOnline);
    window.addEventListener('focus', syncIfVisible);

    return () => {
      unsubscribe();
      document.removeEventListener('visibilitychange', syncIfVisible);
      window.removeEventListener('online', onOnline);
      window.removeEventListener('focus', syncIfVisible);
    };
  }, []);
}
