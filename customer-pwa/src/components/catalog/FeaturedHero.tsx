import { Link } from 'react-router-dom';
import { useEffect, useState } from 'react';
import { BRAND_DISPLAY_NAME } from '../common/BrandLogo';
import { pickHeroImage, resolveCatalogMediaUrl } from '../../utils/images';
import { WebsiteHeroContent } from '../../types/content';

interface FeaturedHeroProps {
  hero?: WebsiteHeroContent | null;
  businessName?: string | null;
}

export function FeaturedHero({ hero, businessName }: FeaturedHeroProps) {
  const brand = businessName?.trim() || BRAND_DISPLAY_NAME;
  const title = hero?.title?.trim() || brand;
  const subtitle =
    hero?.subtitle?.trim() || 'Browse the live menu, order ahead, and collect when it is ready.';
  const fallback = pickHeroImage(0);
  const [imageSrc, setImageSrc] = useState(() => resolveCatalogMediaUrl(hero?.image_path, fallback));

  useEffect(() => {
    setImageSrc(resolveCatalogMediaUrl(hero?.image_path, fallback));
  }, [hero?.image_path, fallback]);

  return (
    <section className="hero-card motion-enter">
      <div className="hero-media" aria-hidden="true">
        <img
          src={imageSrc}
          alt=""
          loading="eager"
          decoding="async"
          fetchPriority="high"
          onError={() => {
            if (imageSrc !== fallback) {
              setImageSrc(fallback);
            }
          }}
        />
      </div>
      <div className="hero-copy">
        <p className="eyebrow">{brand}</p>
        <h1>{title === brand ? 'Fresh coffee, ready when you are' : title}</h1>
        <p>{subtitle}</p>
        <div className="hero-actions">
          <Link to="/menu" className="btn btn-primary btn-lg rounded-pill px-4">
            Explore menu
          </Link>
        </div>
      </div>
    </section>
  );
}
