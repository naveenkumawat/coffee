import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { LoadingSkeleton } from '../components/common/LoadingSkeleton';
import { useAuthStore } from '../stores/authStore';
import { buildLoginRedirect } from '../utils/navigation';

export function ProtectedRoute() {
  const status = useAuthStore((state) => state.status);
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

  return <Outlet />;
}
