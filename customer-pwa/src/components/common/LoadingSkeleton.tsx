interface LoadingSkeletonProps {
  lines?: number;
  cardCount?: number;
  variant?: 'card' | 'list' | 'hero';
}

export function LoadingSkeleton({ lines = 3, cardCount = 1, variant = 'card' }: LoadingSkeletonProps) {
  return (
    <div className="skeleton-stack" aria-hidden="true">
      {Array.from({ length: cardCount }).map((_, cardIndex) => (
        <div className={`skeleton-card ${variant === 'card' ? '' : `is-${variant}`}`.trim()} key={cardIndex}>
          <div className="skeleton-media"></div>
          <div className="skeleton-lines">
            {Array.from({ length: lines }).map((__, lineIndex) => (
              <span key={lineIndex} className="skeleton-line"></span>
            ))}
          </div>
        </div>
      ))}
    </div>
  );
}
