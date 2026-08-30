import { Link } from 'react-router-dom';
import heroArt from '../../assets/images/featured/pic1.png';
import mugArt from '../../assets/images/svg/coffee-mug.svg';

export function FeaturedHero() {
  return (
    <section className="hero-card">
      <div className="hero-copy">
        <p className="eyebrow">Mobile ordering, pickup ready</p>
        <h1>Brew your next order before you arrive.</h1>
        <p>
          Browse the live menu, pick a variant, and let the backend keep pricing and availability accurate.
        </p>
        <div className="hero-actions">
          <Link to="/menu" className="btn btn-primary btn-lg rounded-pill px-4">
            Explore menu
          </Link>
          <span className="hero-pill">
            <img src={mugArt} alt="" />
            PWA foundation
          </span>
        </div>
      </div>
      <div className="hero-media">
        <img src={heroArt} alt="Coffee hero" />
      </div>
    </section>
  );
}
