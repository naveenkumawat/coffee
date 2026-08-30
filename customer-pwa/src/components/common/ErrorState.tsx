interface ErrorStateProps {
  title?: string;
  description: string;
  onRetry?: () => void;
}

export function ErrorState({ title = 'Something went wrong', description, onRetry }: ErrorStateProps) {
  return (
    <section className="state-card state-card-error">
      <span className="state-icon">
        <i className="bi bi-exclamation-octagon"></i>
      </span>
      <h2>{title}</h2>
      <p>{description}</p>
      {onRetry ? (
        <button type="button" className="btn btn-outline-dark rounded-pill px-4" onClick={onRetry}>
          Try again
        </button>
      ) : null}
    </section>
  );
}
