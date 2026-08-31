import { selectHomeSlogan, useContentStore } from '../../stores/contentStore';
import { BrandLogo } from './BrandLogo';

/**
 * Homepage brand header: centered logo + Website Settings slogan.
 */
export function Header() {
  const slogan = useContentStore((state) => selectHomeSlogan(state.content));

  return (
    <header className="home-brand-header">
      <BrandLogo linked size="lg" showWordmark />
      {slogan ? <p className="home-brand-slogan">{slogan}</p> : null}
    </header>
  );
}
