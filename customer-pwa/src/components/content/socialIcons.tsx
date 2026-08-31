import type { ReactNode } from 'react';

export type SocialIconKey =
  | 'facebook'
  | 'whatsapp'
  | 'instagram'
  | 'youtube'
  | 'x'
  | 'tiktok'
  | 'linkedin'
  | (string & {});

/**
 * Controlled social icon registry. Unknown keys fall back to a generic external-link icon.
 */
const SOCIAL_ICON_CLASS: Record<string, string> = {
  facebook: 'bi bi-facebook',
  whatsapp: 'bi bi-whatsapp',
  instagram: 'bi bi-instagram',
  youtube: 'bi bi-youtube',
  x: 'bi bi-twitter-x',
  tiktok: 'bi bi-tiktok',
  linkedin: 'bi bi-linkedin',
};

const FALLBACK_ICON_CLASS = 'bi bi-box-arrow-up-right';

export function resolveSocialIconClass(iconKey: string | null | undefined): string {
  const key = (iconKey ?? '').trim().toLowerCase();

  return SOCIAL_ICON_CLASS[key] ?? FALLBACK_ICON_CLASS;
}

interface SocialIconProps {
  iconKey: string;
  className?: string;
}

export function SocialIcon({ iconKey, className = '' }: SocialIconProps): ReactNode {
  return <i className={`${resolveSocialIconClass(iconKey)} ${className}`.trim()} aria-hidden="true" />;
}
