import { Navigate, Outlet } from 'react-router-dom';
import { LoadingSkeleton } from '../components/common/LoadingSkeleton';
import { useAuthStore } from '../stores/authStore';
import { isWaiterUser } from '../utils/roles';

export function WaiterRoute() {
  const status = useAuthStore((state) => state.status);
  const customer = useAuthStore((state) => state.customer);

  if (status === 'idle' || status === 'initializing') {
    return (
      <div className="page-container">
        <LoadingSkeleton cardCount={2} lines={4} />
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
