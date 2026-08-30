import { Link } from 'react-router-dom';

interface EmptyStateProps {
  title: string;
  description: string;
  actionLabel?: string;
  actionHref?: string;
}

export function EmptyState({ title, description, actionLabel, actionHref }: EmptyStateProps) {
  return (
    <section className="state-card">
      <span className="state-icon">
        <i className="bi bi-cup-hot"></i>
      </span>
      <h2>{title}</h2>
      <p>{description}</p>
      {actionLabel && actionHref ? (
        <Link to={actionHref} className="btn btn-primary btn-lg rounded-pill px-4">
          {actionLabel}
        </Link>
      ) : null}
    </section>
  );
}
