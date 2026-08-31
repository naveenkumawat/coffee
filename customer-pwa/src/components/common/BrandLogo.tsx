import { Link } from 'react-router-dom';
import brandMark from '../../assets/images/app-logo/brand-mark.svg';

interface BrandLogoProps {
  /** When true, wraps the mark + name in a home link. */
  linked?: boolean;
  /** Compact header treatment. */
  size?: 'sm' | 'md' | 'lg';
  /** Show wordmark next to the cup mark. */
  showWordmark?: boolean;
  className?: string;
}

export const BRAND_DISPLAY_NAME = 'The88Coffees';

/**
 * Shared customer brand lockup using the cup mark + The88Coffees wordmark.
 */
export function BrandLogo({
  linked = false,
  size = 'md',
  showWordmark = true,
  className = '',
}: BrandLogoProps) {
  const content = (
    <span className={`brand-lockup is-${size} ${className}`.trim()}>
      <img
        src={brandMark}
        alt={showWordmark ? '' : BRAND_DISPLAY_NAME}
        className="brand-mark-icon"
        width={40}
        height={40}
        decoding="async"
      />
      {showWordmark ? <span className="brand-wordmark">{BRAND_DISPLAY_NAME}</span> : null}
    </span>
  );

  if (!linked) {
    return content;
  }

  return (
    <Link to="/" className="brand-lockup-link" aria-label={`${BRAND_DISPLAY_NAME} home`}>
      {content}
    </Link>
  );
}
