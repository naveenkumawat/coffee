import { RatingSummary } from '../../types/rating';
import { RatingStars } from './RatingStars';

interface ProductRatingSummaryProps {
  summary?: RatingSummary | null;
  compact?: boolean;
  className?: string;
}

export function ProductRatingSummary({
  summary,
  compact = true,
  className = '',
}: ProductRatingSummaryProps) {
  const count = summary?.count ?? 0;
  const average = summary?.average ?? null;

  if (count <= 0 || average === null) {
    if (!compact) {
      return <p className={`product-rating-empty ${className}`.trim()}>No ratings yet</p>;
    }

    return <span className={`product-rating-summary is-new ${className}`.trim()}>New</span>;
  }

  const averageLabel = average.toFixed(1);

  if (compact) {
    return (
      <span className={`product-rating-summary ${className}`.trim()} aria-label={`${averageLabel} stars from ${count} ratings`}>
        <i className="bi bi-star-fill" aria-hidden="true" />
        <strong>{averageLabel}</strong>
        <span>({count})</span>
      </span>
    );
  }

  return (
    <div className={`product-rating-summary is-detail ${className}`.trim()}>
      <RatingStars value={average} size="md" label={`${averageLabel} out of 5`} />
      <div className="product-rating-summary-copy">
        <strong>{averageLabel}</strong>
        <span>
          {count} rating{count === 1 ? '' : 's'}
        </span>
      </div>
    </div>
  );
}
