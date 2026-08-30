import { useCallback, useEffect, useRef, useState } from 'react';

interface ServiceWorkerUpdateState {
  needRefresh: boolean;
  applyUpdate: () => void;
  dismiss: () => void;
}

export function useServiceWorkerUpdate(): ServiceWorkerUpdateState {
  const [needRefresh, setNeedRefresh] = useState(false);
  const registrationRef = useRef<ServiceWorkerRegistration | null>(null);

  useEffect(() => {
    if (!('serviceWorker' in navigator)) {
      return;
    }

    // Vite/dev serves index.html for unknown paths; registering /sw.js there causes MIME errors.
    // Also clear any stale production SW that may still control the localhost:4173 origin.
    if (import.meta.env.DEV) {
      void navigator.serviceWorker.getRegistrations().then((registrations) => {
        registrations.forEach((registration) => {
          void registration.unregister();
        });
      });

      return;
    }

    let cancelled = false;
    let updateIntervalId: number | undefined;

    async function register(): Promise<void> {
      try {
        const registration = await navigator.serviceWorker.register('/sw.js', { scope: '/' });

        if (cancelled) {
          return;
        }

        registrationRef.current = registration;

        const announceWaiting = (): void => {
          if (registration.waiting && navigator.serviceWorker.controller) {
            setNeedRefresh(true);
          }
        };

        announceWaiting();

        registration.addEventListener('updatefound', () => {
          const installing = registration.installing;

          if (!installing) {
            return;
          }

          installing.addEventListener('statechange', () => {
            if (installing.state === 'installed' && navigator.serviceWorker.controller) {
              setNeedRefresh(true);
            }
          });
        });

        updateIntervalId = window.setInterval(() => {
          void registration.update();
        }, 60 * 60 * 1000);
      } catch {
        // Installability still works from the manifest even if SW registration fails locally.
      }
    }

    void register();

    return () => {
      cancelled = true;

      if (updateIntervalId !== undefined) {
        window.clearInterval(updateIntervalId);
      }

      registrationRef.current = null;
    };
  }, []);

  const applyUpdate = useCallback(() => {
    const waiting = registrationRef.current?.waiting;

    if (!waiting) {
      window.location.reload();
      return;
    }

    waiting.postMessage({ type: 'SKIP_WAITING' });
    waiting.addEventListener('statechange', () => {
      if (waiting.state === 'activated') {
        window.location.reload();
      }
    });
  }, []);

  const dismiss = useCallback(() => {
    setNeedRefresh(false);
  }, []);

  return {
    needRefresh,
    applyUpdate,
    dismiss
  };
}
