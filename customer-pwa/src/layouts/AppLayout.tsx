import { Suspense, useEffect, useState } from 'react';
import { Outlet, useLocation } from 'react-router-dom';
import { SiteFooter } from '../components/content/SiteFooter';
import { BottomNavigation } from '../components/navigation/BottomNavigation';
import { LoadingSkeleton } from '../components/common/LoadingSkeleton';
import { ServiceWorkerUpdateBanner } from '../components/common/ServiceWorkerUpdateBanner';
import { ToastHost } from '../components/common/ToastHost';
import { useAppBootstrap } from '../hooks/useAppBootstrap';
import { useNetworkStatus } from '../hooks/useNetworkStatus';
import { useServiceWorkerUpdate } from '../hooks/useServiceWorkerUpdate';
import { selectBrandName, useContentStore } from '../stores/contentStore';
import { clearChunkRecoveryFlag } from '../utils/chunkRecovery';

export function AppLayout() {
  const networkStatus = useNetworkStatus();
  const { needRefresh, applyUpdate, dismiss } = useServiceWorkerUpdate();
  const brandName = useContentStore((state) => selectBrandName(state.content));
  const location = useLocation();
  const [hasStickyCta, setHasStickyCta] = useState(false);

  useAppBootstrap();

  useEffect(() => {
    // Clear recovery guard only after a short healthy window so a failed
    // post-reload boot cannot loop forever.
    const timer = window.setTimeout(() => {
      clearChunkRecoveryFlag();
    }, 4000);

    return () => window.clearTimeout(timer);
  }, []);

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
      <ServiceWorkerUpdateBanner
        visible={needRefresh}
        onRefresh={applyUpdate}
        onDismiss={dismiss}
        brandName={brandName}
      />
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
        {!hasStickyCta ? <SiteFooter /> : null}
      </main>
      <ToastHost elevateForStickyCta={hasStickyCta} />
      <BottomNavigation />
    </div>
  );
}
