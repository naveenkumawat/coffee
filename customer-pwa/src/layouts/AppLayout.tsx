import { Outlet } from 'react-router-dom';
import { BottomNavigation } from '../components/navigation/BottomNavigation';
import { useAppBootstrap } from '../hooks/useAppBootstrap';
import { useOnlineStatus } from '../hooks/useOnlineStatus';

export function AppLayout() {
  const isOnline = useOnlineStatus();

  useAppBootstrap();

  return (
    <div className="app-shell">
      {!isOnline ? (
        <div className="offline-banner">
          You’re offline. Live cart, account, and order data will refresh when the connection returns.
        </div>
      ) : null}
      <main className="app-main">
        <Outlet />
      </main>
      <BottomNavigation />
    </div>
  );
}
