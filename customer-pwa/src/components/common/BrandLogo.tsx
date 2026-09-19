import { useState } from 'react';
import { Link } from 'react-router-dom';
import { DEFAULT_BRAND_NAME } from '../../types/content';
import {
  selectBrandDisplayMode,
  selectBrandLogoUrl,
  selectBrandName,
  selectHomeSlogan,
  useContentStore,
} from '../../stores/contentStore';
import { resolveCatalogMediaUrl } from '../../utils/images';

interface BrandLogoProps {
  /** When true, wraps the lockup in a home link. */
  linked?: boolean;
  /**
   * hero = prominent home branding.
   * compact = auth, waiter, error, and other chrome (never 200–300px logos).
   */
  placement?: 'hero' | 'compact';
  /** Compact size only; ignored for hero. */
  size?: 'sm' | 'md';
  /** Optional override; defaults to Website Settings brand name. */
  name?: string;
  className?: string;
}

/** Fallback brand for non-React callers before content loads. */
export const BRAND_DISPLAY_NAME = DEFAULT_BRAND_NAME;

/**
 * Shared customer brand lockup: uploaded Website Settings logo and optional name/tagline.
 */
export function BrandLogo({
  linked = false,
  placement = 'compact',
  size = 'md',
  name,
  className = '',
}: BrandLogoProps) {
  const configuredName = useContentStore((state) => selectBrandName(state.content));
  const tagline = useContentStore((state) => selectHomeSlogan(state.content));
  const rawLogoUrl = useContentStore((state) => selectBrandLogoUrl(state.content));
  const displayMode = useContentStore((state) => selectBrandDisplayMode(state.content));
  const [logoFailed, setLogoFailed] = useState(false);
  const displayName = name?.trim() || configuredName;
  const resolvedLogo = rawLogoUrl ? resolveCatalogMediaUrl(rawLogoUrl, '') : '';
  const showLogo = Boolean(resolvedLogo) && !logoFailed;
  const showName = !showLogo || displayMode === 'logo_name' || displayMode === 'logo_name_tagline';
  const showTagline = placement === 'hero' && displayMode === 'logo_name_tagline';
  const layoutClass = !showLogo
    ? 'is-text-only'
    : displayMode === 'logo'
      ? 'is-logo-only'
      : displayMode === 'logo_name'
        ? 'is-logo-name'
        : 'is-logo-name-tagline';

  const content = (
    <span
      className={`brand-lockup is-${placement} is-${size} ${layoutClass} ${className}`.trim()}
    >
      {showLogo ? (
        <img
          src={resolvedLogo}
          alt={showName ? '' : displayName}
          className="brand-logo-image"
          decoding="async"
          onError={() => setLogoFailed(true)}
        />
      ) : null}
      {showName || showTagline ? (
        <span className="brand-lockup-copy">
          {showName ? <span className="brand-wordmark">{displayName}</span> : null}
          {showTagline && tagline ? <span className="brand-tagline">{tagline}</span> : null}
        </span>
      ) : null}
      {!showLogo && !showName ? <span className="visually-hidden">{displayName}</span> : null}
    </span>
  );

  if (!linked) {
    return content;
  }

  return (
    <Link
      to="/"
      className={`brand-lockup-link is-${placement}`}
      {...(showName ? {} : { 'aria-label': `${displayName} home` })}
    >
      {content}
    </Link>
  );
}
