import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { LoadingSkeleton } from '../components/common/LoadingSkeleton';
import { useAuthStore } from '../stores/authStore';
import { buildLoginRedirect } from '../utils/navigation';
import { isWaiter } from '../utils/roles';

export function ProtectedRoute() {
  const status = useAuthStore((state) => state.status);
  const customer = useAuthStore((state) => state.customer);
  const location = useLocation();

  if (status === 'idle' || status === 'initializing') {
    return (
      <div className="page-container">
        <LoadingSkeleton cardCount={2} lines={4} />
      </div>
    );
  }

  if (status !== 'authenticated') {
    return (
      <Navigate
        to={buildLoginRedirect(location.pathname, location.search)}
        replace
        state={{ from: `${location.pathname}${location.search}` }}
      />
    );
  }

  if (isWaiter(customer) && !location.pathname.startsWith('/account')) {
    return <Navigate to="/waiter" replace />;
  }

  return <Outlet />;
}
