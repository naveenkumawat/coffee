import { Navigate, Outlet, useSearchParams } from 'react-router-dom';
import { LoadingSkeleton } from '../components/common/LoadingSkeleton';
import { useAuthStore } from '../stores/authStore';
import { normalizeRedirectPath } from '../utils/navigation';
import { isWaiter } from '../utils/roles';

export function GuestRoute() {
  const status = useAuthStore((state) => state.status);
  const customer = useAuthStore((state) => state.customer);
  const [searchParams] = useSearchParams();

  if (status === 'idle' || status === 'initializing') {
    return (
      <div className="page-container">
        <LoadingSkeleton cardCount={2} lines={4} />
      </div>
    );
  }

  if (status === 'authenticated') {
    if (isWaiter(customer)) {
      return <Navigate to="/waiter" replace />;
    }

    return <Navigate to={normalizeRedirectPath(searchParams.get('redirect'))} replace />;
  }

  return <Outlet />;
}
