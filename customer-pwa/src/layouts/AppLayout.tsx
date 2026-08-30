import { Suspense, useEffect, useState } from 'react';
import { Outlet, useLocation } from 'react-router-dom';
import { BottomNavigation } from '../components/navigation/BottomNavigation';
import { LoadingSkeleton } from '../components/common/LoadingSkeleton';
import { ServiceWorkerUpdateBanner } from '../components/common/ServiceWorkerUpdateBanner';
import { ToastHost } from '../components/common/ToastHost';
import { useAppBootstrap } from '../hooks/useAppBootstrap';
import { useNetworkStatus } from '../hooks/useNetworkStatus';
import { useServiceWorkerUpdate } from '../hooks/useServiceWorkerUpdate';

export function AppLayout() {
  const networkStatus = useNetworkStatus();
  const { needRefresh, applyUpdate, dismiss } = useServiceWorkerUpdate();
  const location = useLocation();
  const [hasStickyCta, setHasStickyCta] = useState(false);

  useAppBootstrap();

  useEffect(() => {
    const update = (): void => {
      setHasStickyCta(Boolean(document.querySelector('.sticky-action-bar')));
    };

    update();
    const frame = window.requestAnimationFrame(update);

    return () => window.cancelAnimationFrame(frame);
  }, [location.pathname, location.search]);

  return (
    <div className="app-shell">
      <a href="#main-content" className="skip-link">
        Skip to content
      </a>
      <ServiceWorkerUpdateBanner visible={needRefresh} onRefresh={applyUpdate} onDismiss={dismiss} />
      {networkStatus === 'offline' ? (
        <div className="offline-banner is-offline" role="status" aria-live="polite">
          You’re offline. Browse cached pages, but cart, checkout, account, and orders need a connection.
        </div>
      ) : null}
      {networkStatus === 'restored' ? (
        <div className="offline-banner is-restored" role="status" aria-live="polite">
          Back online. Your live cart and account data will refresh.
        </div>
      ) : null}
      <main className="app-main" id="main-content">
        <Suspense fallback={<LoadingSkeleton cardCount={2} lines={4} />}>
          <Outlet />
        </Suspense>
      </main>
      <ToastHost elevateForStickyCta={hasStickyCta} />
      <BottomNavigation />
    </div>
  );
}
