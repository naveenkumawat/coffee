import { Link } from 'react-router-dom';

interface EmptyStateProps {
  title: string;
  description: string;
  actionLabel?: string;
  actionHref?: string;
  onAction?: () => void;
}

export function EmptyState({ title, description, actionLabel, actionHref, onAction }: EmptyStateProps) {
  return (
    <section className="state-card">
      <span className="state-icon">
        <i className="bi bi-cup-hot"></i>
      </span>
      <h2>{title}</h2>
      <p>{description}</p>
      {actionLabel && onAction ? (
        <button type="button" className="btn btn-primary btn-lg rounded-pill px-4" onClick={onAction}>
          {actionLabel}
        </button>
      ) : null}
      {actionLabel && actionHref && !onAction ? (
        <Link to={actionHref} className="btn btn-primary btn-lg rounded-pill px-4">
          {actionLabel}
        </Link>
      ) : null}
    </section>
  );
}
