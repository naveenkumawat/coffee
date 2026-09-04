import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { fetchCategories } from '../api/catalog';
import { ApiError } from '../api/client';
import { fetchHome } from '../api/home';
import { LandingCampaignSurface } from '../components/campaigns/LandingCampaignSurface';
import { HomeProductSection } from '../components/catalog/HomeProductSection';
import { EmptyState } from '../components/common/EmptyState';
import { ErrorState } from '../components/common/ErrorState';
import { Header } from '../components/common/Header';
import { LoadingSkeleton } from '../components/common/LoadingSkeleton';
import { ProductCategory } from '../types/catalog';
import { HomeCampaigns, HomeSection } from '../types/home';
import { getOrCreateCampaignSessionKey } from '../utils/campaignSession';
import { getOrCreateVisitorId } from '../utils/visitorId';

export function HomePage() {
  const [sections, setSections] = useState<HomeSection[]>([]);
  const [campaigns, setCampaigns] = useState<HomeCampaigns>({ banner: null, inline: null });
  const [categories, setCategories] = useState<ProductCategory[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  useEffect(() => {
    async function load(): Promise<void> {
      setIsLoading(true);
      setErrorMessage(null);

      try {
        const [homeResponse, categoryResponse] = await Promise.all([
          fetchHome({
            placement: 'home',
            visitor_key: getOrCreateVisitorId(),
            session_key: getOrCreateCampaignSessionKey(),
          }),
          fetchCategories(),
        ]);

        setSections(homeResponse.data.sections ?? []);
        setCampaigns(homeResponse.data.campaigns ?? { banner: null, inline: null });
        setCategories(categoryResponse.data);
      } catch (error) {
        const message = error instanceof ApiError ? error.message : 'Unable to load the cafe menu right now.';
        setErrorMessage(message);
      } finally {
        setIsLoading(false);
      }
    }

    void load();
  }, []);

  return (
    <div className="page-container home-page">
      <Header />

      <section className="section-shell home-categories">
        <div className="section-header">
          <div>
            <h2>Browse the menu</h2>
          </div>
          <Link to="/menu" className="text-link">
            Full menu
          </Link>
        </div>
        <div className="category-pills static home-category-rail" role="list">
          {categories.slice(0, 6).map((category) => (
            <Link
              key={category.id}
              to={`/menu?category=${category.id}`}
              className="category-pill"
              role="listitem"
            >
              {category.name}
            </Link>
          ))}
        </div>
      </section>

      {isLoading ? <LoadingSkeleton cardCount={2} lines={4} variant="list" /> : null}
      {errorMessage ? <ErrorState description={errorMessage} onRetry={() => window.location.reload()} /> : null}

      {!isLoading && !errorMessage ? (
        <>
          <LandingCampaignSurface campaign={campaigns.banner} surface="banner" placement="home" />
          {sections.length > 0 ? (
            sections.map((section) => (
              <HomeProductSection key={section.id} section={section} placement="home_rail" />
            ))
          ) : (
            <EmptyState
              title="Menu coming soon"
              description="Browse the full menu to find something to order for pickup."
              actionLabel="Open menu"
              actionHref="/menu"
            />
          )}
          <LandingCampaignSurface campaign={campaigns.inline} surface="inline" placement="home" />
        </>
      ) : null}
    </div>
  );
}
