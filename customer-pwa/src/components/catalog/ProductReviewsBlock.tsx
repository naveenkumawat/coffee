import { Link } from 'react-router-dom';
import { PublicProductReview, RatingSummary } from '../../types/rating';
import { formatDateTime } from '../../utils/format';
import { ProductRatingSummary } from './ProductRatingSummary';
import { RatingStars } from './RatingStars';

interface ProductReviewsBlockProps {
  summary: RatingSummary | null | undefined;
  reviews: PublicProductReview[];
  productId: number;
  previewLimit?: number;
  showViewAll?: boolean;
  compact?: boolean;
}

function distributionEntries(summary: RatingSummary | null | undefined): Array<[number, number]> {
  const distribution = summary?.distribution;
  if (!distribution) {
    return [];
  }

  return [5, 4, 3, 2, 1].map((star) => {
    const raw = (distribution as Record<string | number, number>)[star]
      ?? (distribution as Record<string | number, number>)[String(star)]
      ?? 0;

    return [star, Number(raw) || 0] as [number, number];
  });
}

export function ProductReviewsBlock({
  summary,
  reviews,
  productId,
  previewLimit = 3,
  showViewAll = true,
  compact = false,
}: ProductReviewsBlockProps) {
  const visibleReviews = reviews.slice(0, previewLimit);
  const bars = distributionEntries(summary);
  const total = summary?.count ?? 0;

  return (
    <section className={`product-reviews-block ${compact ? 'is-compact' : ''}`.trim()}>
      <ProductRatingSummary summary={summary} compact={false} />

      {bars.some(([, count]) => count > 0) ? (
        <div className="rating-distribution" aria-label="Rating distribution">
          {bars.map(([star, count]) => {
            const width = total > 0 ? Math.round((count / total) * 100) : 0;

            return (
              <div key={star} className="rating-distribution-row">
                <span>{star} ★</span>
                <div className="rating-distribution-track">
                  <span style={{ width: `${width}%` }} />
                </div>
                <span>{count}</span>
              </div>
            );
          })}
        </div>
      ) : null}

      {visibleReviews.length > 0 ? (
        <ul className="product-review-list">
          {visibleReviews.map((review) => (
            <li key={review.id} className="product-review-item">
              <div className="product-review-head">
                <strong>{review.customer_display_name}</strong>
                <RatingStars value={review.rating} size="sm" />
              </div>
              {review.review ? <p>{review.review}</p> : null}
              <div className="product-review-meta">
                {review.is_verified_purchase ? <span>Verified purchase</span> : null}
                {review.created_at ? <time dateTime={review.created_at}>{formatDateTime(review.created_at)}</time> : null}
              </div>
            </li>
          ))}
        </ul>
      ) : (
        <p className="product-overlay-note">No written reviews yet.</p>
      )}

      {showViewAll && (reviews.length > previewLimit || (summary?.count ?? 0) > previewLimit) ? (
        <Link className="link-button" to={`/menu/${productId}`}>
          View all reviews
        </Link>
      ) : null}
    </section>
  );
}
