import { useId, useState } from 'react';

interface RatingStarsProps {
  value: number;
  max?: number;
  interactive?: boolean;
  size?: 'sm' | 'md' | 'lg';
  onChange?: (value: number) => void;
  label?: string;
  className?: string;
}

export function RatingStars({
  value,
  max = 5,
  interactive = false,
  size = 'md',
  onChange,
  label,
  className = '',
}: RatingStarsProps) {
  const groupId = useId();
  const [preview, setPreview] = useState<number | null>(null);
  const displayValue = preview ?? value;
  const roundedReadonly = Math.round(value * 2) / 2;

  function starFill(index: number): 'full' | 'half' | 'empty' {
    const compare = interactive ? displayValue : roundedReadonly;

    if (compare >= index) {
      return 'full';
    }

    if (!interactive && compare >= index - 0.5) {
      return 'half';
    }

    return 'empty';
  }

  return (
    <div
      className={`rating-stars size-${size} ${interactive ? 'is-interactive' : 'is-readonly'} ${className}`.trim()}
      role={interactive ? 'radiogroup' : 'img'}
      aria-label={label ?? (interactive ? 'Rating' : `${value} out of ${max} stars`)}
      onMouseLeave={() => {
        if (interactive) {
          setPreview(null);
        }
      }}
    >
      {Array.from({ length: max }, (_, offset) => {
        const star = offset + 1;
        const fill = starFill(star);

        if (!interactive) {
          return (
            <span key={star} className={`rating-star is-${fill}`} aria-hidden="true">
              <i className="bi bi-star-fill" />
            </span>
          );
        }

        return (
          <button
            key={star}
            type="button"
            className={`rating-star is-${fill}`}
            role="radio"
            aria-checked={value === star}
            aria-label={`Rate ${star} out of ${max}`}
            id={`${groupId}-star-${star}`}
            onMouseEnter={() => setPreview(star)}
            onFocus={() => setPreview(star)}
            onBlur={() => setPreview(null)}
            onClick={() => onChange?.(star)}
          >
            <i className="bi bi-star-fill" aria-hidden="true" />
          </button>
        );
      })}
    </div>
  );
}
