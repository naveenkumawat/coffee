import { useEffect, useRef, useState } from 'react';

export type NetworkStatus = 'online' | 'offline' | 'restored';

export function useNetworkStatus(): NetworkStatus {
  const [status, setStatus] = useState<NetworkStatus>(() => (navigator.onLine ? 'online' : 'offline'));
  const wasOfflineRef = useRef(!navigator.onLine);

  useEffect(() => {
    let restoreTimer: number | undefined;

    function handleOnline(): void {
      if (wasOfflineRef.current) {
        setStatus('restored');
        window.clearTimeout(restoreTimer);
        restoreTimer = window.setTimeout(() => {
          setStatus('online');
          wasOfflineRef.current = false;
        }, 2500);
      } else {
        setStatus('online');
      }
    }

    function handleOffline(): void {
      wasOfflineRef.current = true;
      window.clearTimeout(restoreTimer);
      setStatus('offline');
    }

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    return () => {
      window.clearTimeout(restoreTimer);
      window.removeEventListener('online', handleOnline);
      window.removeEventListener('offline', handleOffline);
    };
  }, []);

  return status;
}
