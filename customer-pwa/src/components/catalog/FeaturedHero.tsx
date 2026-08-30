import { Link } from 'react-router-dom';
import mugArt from '../../assets/images/svg/coffee-mug.svg';
import { pickHeroImage } from '../../utils/images';
import { resolveMediaUrl } from '../../utils/content';
import { WebsiteHeroContent } from '../../types/content';

interface FeaturedHeroProps {
  hero?: WebsiteHeroContent | null;
  businessName?: string | null;
}

export function FeaturedHero({ hero, businessName }: FeaturedHeroProps) {
  const title = hero?.title?.trim() || businessName?.trim() || 'Order ahead for pickup';
  const subtitle =
    hero?.subtitle?.trim() ||
    'Browse the live menu, pick what you want, and pick up when it is ready.';
  const imageSrc = resolveMediaUrl(hero?.image_path, pickHeroImage(0));

  return (
    <section className="hero-card">
      <div className="hero-copy">
        <p className="eyebrow">Mobile ordering, pickup ready</p>
        <h1>{title}</h1>
        <p>{subtitle}</p>
        <div className="hero-actions">
          <Link to="/menu" className="btn btn-primary btn-lg rounded-pill px-4">
            Explore menu
          </Link>
          <span className="hero-pill">
            <img src={mugArt} alt="" />
            Pickup ready
          </span>
        </div>
      </div>
      <div className="hero-media">
        <img src={imageSrc} alt="" loading="eager" decoding="async" fetchPriority="high" />
      </div>
    </section>
  );
}
