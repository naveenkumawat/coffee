import { Link } from 'react-router-dom';
import brandMark from '../../assets/images/app-logo/brand-mark.svg';
import { DEFAULT_BRAND_NAME } from '../../types/content';
import { selectBrandName, useContentStore } from '../../stores/contentStore';

interface BrandLogoProps {
  /** When true, wraps the mark + name in a home link. */
  linked?: boolean;
  /** Compact header treatment. */
  size?: 'sm' | 'md' | 'lg';
  /** Show wordmark next to the cup mark. */
  showWordmark?: boolean;
  /** Optional override; defaults to Website Settings business name. */
  name?: string;
  className?: string;
}

/** Fallback brand for non-React callers before content loads. */
export const BRAND_DISPLAY_NAME = DEFAULT_BRAND_NAME;

/**
 * Shared customer brand lockup using the cup mark + configured business name.
 */
export function BrandLogo({
  linked = false,
  size = 'md',
  showWordmark = true,
  name,
  className = '',
}: BrandLogoProps) {
  const configuredName = useContentStore((state) => selectBrandName(state.content));
  const displayName = name?.trim() || configuredName;

  const content = (
    <span className={`brand-lockup is-${size} ${className}`.trim()}>
      <img
        src={brandMark}
        alt={showWordmark ? '' : displayName}
        className="brand-mark-icon"
        width={40}
        height={40}
        decoding="async"
      />
      {showWordmark ? <span className="brand-wordmark">{displayName}</span> : null}
    </span>
  );

  if (!linked) {
    return content;
  }

  return (
    <Link to="/" className="brand-lockup-link" aria-label={`${displayName} home`}>
      {content}
    </Link>
  );
}
