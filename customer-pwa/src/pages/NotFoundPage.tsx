import { EmptyState } from '../components/common/EmptyState';
import { PageHeader } from '../components/common/PageHeader';

export function NotFoundPage() {
  return (
    <div className="page-container">
      <PageHeader title="Page not found" showBack />
      <EmptyState
        title="Nothing brewing here"
        description="This route doesn’t exist in the customer PWA foundation."
        actionLabel="Go home"
        actionHref="/"
      />
    </div>
  );
}
