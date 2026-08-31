import { BrandLogo } from './BrandLogo';

const HOME_SLOGAN = 'Sip. Relax. Enjoy.';

/**
 * Homepage brand header: centered logo + slogan only.
 */
export function Header() {
  return (
    <header className="home-brand-header">
      <BrandLogo linked size="lg" showWordmark />
      <p className="home-brand-slogan">{HOME_SLOGAN}</p>
    </header>
  );
}
