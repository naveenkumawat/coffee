import { Suspense } from 'react';
import { Outlet } from 'react-router-dom';
import { BottomNavigation } from '../components/navigation/BottomNavigation';
import { LoadingSkeleton } from '../components/common/LoadingSkeleton';
import { ServiceWorkerUpdateBanner } from '../components/common/ServiceWorkerUpdateBanner';
import { useAppBootstrap } from '../hooks/useAppBootstrap';
import { useNetworkStatus } from '../hooks/useNetworkStatus';
import { useServiceWorkerUpdate } from '../hooks/useServiceWorkerUpdate';

export function AppLayout() {
  const networkStatus = useNetworkStatus();
  const { needRefresh, applyUpdate, dismiss } = useServiceWorkerUpdate();

  useAppBootstrap();

  return (
    <div className="app-shell">
      <ServiceWorkerUpdateBanner visible={needRefresh} onRefresh={applyUpdate} onDismiss={dismiss} />
      {networkStatus === 'offline' ? (
        <div className="offline-banner is-offline" role="status" aria-live="polite">
          You’re offline. Cached screens may open, but cart, checkout, account, and orders need a connection.
        </div>
      ) : null}
      {networkStatus === 'restored' ? (
        <div className="offline-banner is-restored" role="status" aria-live="polite">
          Back online. Refreshing live cart and account data.
        </div>
      ) : null}
      <main className="app-main" id="main-content">
        <Suspense fallback={<LoadingSkeleton cardCount={2} lines={4} />}>
          <Outlet />
        </Suspense>
      </main>
      <BottomNavigation />
    </div>
  );
}
