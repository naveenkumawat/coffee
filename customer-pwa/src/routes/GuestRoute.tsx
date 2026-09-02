import { Navigate, Outlet, useSearchParams } from 'react-router-dom';
import { LoadingSkeleton } from '../components/common/LoadingSkeleton';
import { useAuthStore } from '../stores/authStore';
import { normalizeRedirectPath } from '../utils/navigation';
import { isWaiter } from '../utils/roles';

export function GuestRoute() {
  const status = useAuthStore((state) => state.status);
  const customer = useAuthStore((state) => state.customer);
  const bootstrap = useAuthStore((state) => state.bootstrap);
  const sessionCheckFailed = useAuthStore((state) => state.sessionCheckFailed);
  const [searchParams] = useSearchParams();

  if (status === 'idle' || status === 'initializing' || status === 'session_unknown') {
    return (
      <div className="page-container">
        {sessionCheckFailed ? (
          <div className="auth-session-recovery motion-enter" role="alert">
            <p>We couldn’t verify your session. Check your connection, then try again.</p>
            <button type="button" className="btn btn-primary rounded-pill" onClick={() => void bootstrap()}>
              Retry
            </button>
          </div>
        ) : (
          <LoadingSkeleton cardCount={2} lines={4} />
        )}
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
