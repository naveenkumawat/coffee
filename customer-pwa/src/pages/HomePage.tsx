import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { fetchCategories } from '../api/catalog';
import { ApiError } from '../api/client';
import { fetchHome } from '../api/home';
import { HomeProductSection } from '../components/catalog/HomeProductSection';
import { EmptyState } from '../components/common/EmptyState';
import { ErrorState } from '../components/common/ErrorState';
import { Header } from '../components/common/Header';
import { LoadingSkeleton } from '../components/common/LoadingSkeleton';
import { RecommendationSection } from '../components/recommendations/RecommendationSection';
import { ProductCategory } from '../types/catalog';
import { HomeSection } from '../types/home';

export function HomePage() {
  const [sections, setSections] = useState<HomeSection[]>([]);
  const [categories, setCategories] = useState<ProductCategory[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  useEffect(() => {
    async function load(): Promise<void> {
      setIsLoading(true);
      setErrorMessage(null);

      try {
        const [homeResponse, categoryResponse] = await Promise.all([fetchHome(), fetchCategories()]);

        setSections(homeResponse.data.sections ?? []);
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
          <RecommendationSection context="home" placement="home_rail" limit={8} title="For you" />
          {sections.length > 0 ? (
            sections.map((section) => (
              <HomeProductSection
                key={section.id}
                title={section.title}
                subtitle={section.subtitle}
                products={section.products}
              />
            ))
          ) : (
            <EmptyState
              title="Menu coming soon"
              description="Browse the full menu to find something to order for pickup."
              actionLabel="Open menu"
              actionHref="/menu"
            />
          )}
        </>
      ) : null}
    </div>
  );
}
