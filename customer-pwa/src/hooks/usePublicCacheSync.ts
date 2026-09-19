import { useEffect } from 'react';
import { applyServerCacheVersion, syncPublicCacheVersion } from '../cache/version';
import { realtimeConnection } from '../realtime/RealtimeConnection';

export function usePublicCacheSync(): void {
  useEffect(() => {
    void syncPublicCacheVersion();
    void realtimeConnection.connectPublic();

    const unsubscribe = realtimeConnection.onPublicCacheInvalidated((version) => {
      void applyServerCacheVersion(version);
    });

    const syncIfVisible = (): void => {
      if (!document.hidden) {
        void syncPublicCacheVersion();
      }
    };

    const onOnline = (): void => {
      void syncPublicCacheVersion(true);
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
