import { BrandLogo } from './BrandLogo';

/**
 * Homepage brand header: centered lockup driven by Website Settings display mode.
 */
export function Header() {
  return (
    <header className="home-brand-header">
      <BrandLogo linked placement="hero" />
    </header>
  );
}
