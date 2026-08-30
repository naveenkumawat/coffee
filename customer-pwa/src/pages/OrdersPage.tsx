import { EmptyState } from '../components/common/EmptyState';
import { PageHeader } from '../components/common/PageHeader';

export function OrdersPage() {
  return (
    <div className="page-container">
      <PageHeader title="Orders" description="Order history and tracking will land in a later slice after checkout confirmation." />
      <EmptyState
        title="Orders UI pending"
        description="The API endpoints exist, and checkout confirmation now links here, but the full customer PWA order history and tracking experience is still intentionally deferred."
        actionLabel="Browse menu"
        actionHref="/menu"
      />
    </div>
  );
}
