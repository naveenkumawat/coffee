import { Link, Navigate, Outlet, useLocation } from 'react-router-dom';
import { LoadingSkeleton } from '../components/common/LoadingSkeleton';
import { useAuthStore } from '../stores/authStore';
import { buildLoginRedirect } from '../utils/navigation';
import { isWaiterUser } from '../utils/roles';

export function WaiterRoute() {
  const status = useAuthStore((state) => state.status);
  const customer = useAuthStore((state) => state.customer);
  const bootstrap = useAuthStore((state) => state.bootstrap);
  const sessionCheckFailed = useAuthStore((state) => state.sessionCheckFailed);
  const location = useLocation();

  if (status === 'idle' || status === 'initializing' || status === 'session_unknown') {
    return (
      <div className="page-container">
        {sessionCheckFailed ? (
          <div className="auth-session-recovery motion-enter" role="alert">
            <p>We couldn’t verify your session. Check your connection, then try again.</p>
            <div className="auth-session-recovery-actions">
              <button type="button" className="btn btn-primary rounded-pill" onClick={() => void bootstrap()}>
                Retry
              </button>
              <Link
                className="btn btn-secondary rounded-pill"
                to={buildLoginRedirect(location.pathname, location.search)}
              >
                Sign in again
              </Link>
            </div>
          </div>
        ) : (
          <LoadingSkeleton cardCount={2} lines={4} />
        )}
      </div>
    );
  }

  if (status !== 'authenticated') {
    return <Navigate to="/login?redirect=/waiter" replace />;
  }

  if (!isWaiterUser(customer)) {
    return <Navigate to="/" replace />;
  }

  return <Outlet />;
}
