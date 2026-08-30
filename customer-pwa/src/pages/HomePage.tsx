import { useEffect, useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import {
  fetchBestsellerProducts,
  fetchCategories,
  fetchFeaturedProducts,
  fetchNewProducts
} from '../api/catalog';
import { fetchWebsiteContent } from '../api/content';
import { ApiError } from '../api/client';
import { FeaturedHero } from '../components/catalog/FeaturedHero';
import { ProductCard } from '../components/catalog/ProductCard';
import { HomeContentSections } from '../components/content/HomeContentSections';
import { EmptyState } from '../components/common/EmptyState';
import { ErrorState } from '../components/common/ErrorState';
import { Header } from '../components/common/Header';
import { LoadingSkeleton } from '../components/common/LoadingSkeleton';
import { Product, ProductCategory } from '../types/catalog';
import { WebsiteContent } from '../types/content';
import { useCartStore } from '../stores/cartStore';
import { buildLoginRedirect } from '../utils/navigation';

export function HomePage() {
  const [featuredProducts, setFeaturedProducts] = useState<Product[]>([]);
  const [newProducts, setNewProducts] = useState<Product[]>([]);
  const [bestsellerProducts, setBestsellerProducts] = useState<Product[]>([]);
  const [categories, setCategories] = useState<ProductCategory[]>([]);
  const [websiteContent, setWebsiteContent] = useState<WebsiteContent | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [pendingProductId, setPendingProductId] = useState<number | null>(null);
  const count = useCartStore((state) => state.count);
  const addItem = useCartStore((state) => state.addItem);
  const navigate = useNavigate();
  const location = useLocation();

  useEffect(() => {
    async function load(): Promise<void> {
      setIsLoading(true);
      setErrorMessage(null);

      try {
        const [featuredResponse, categoryResponse, newResponse, bestsellerResponse] = await Promise.all([
          fetchFeaturedProducts(),
          fetchCategories(),
          fetchNewProducts(),
          fetchBestsellerProducts()
        ]);

        setFeaturedProducts(featuredResponse.data);
        setCategories(categoryResponse.data);
        setNewProducts(newResponse.data);
        setBestsellerProducts(bestsellerResponse.data);

        try {
          const contentResponse = await fetchWebsiteContent();
          setWebsiteContent(contentResponse.data);
        } catch {
          setWebsiteContent(null);
        }
      } catch (error) {
        const message = error instanceof ApiError ? error.message : 'Unable to load the Coffee storefront.';
        setErrorMessage(message);
      } finally {
        setIsLoading(false);
      }
    }

    void load();
  }, []);

  async function handleAddToCart(product: Product): Promise<void> {
    if (!product.default_variant) {
      return;
    }

    setPendingProductId(product.id);

    try {
      await addItem({
        product_variant_id: product.default_variant.id,
        quantity: 1
      });
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) {
        navigate(buildLoginRedirect(location.pathname, location.search));
      } else {
        setErrorMessage(error instanceof ApiError ? error.message : 'Unable to add the item right now.');
      }
    } finally {
      setPendingProductId(null);
    }
  }

  return (
    <div className="page-container">
      <Header cartCount={count} />
      <FeaturedHero hero={websiteContent?.hero} businessName={websiteContent?.business.name} />

      <section className="section-shell">
        <div className="section-header">
          <div>
            <p className="eyebrow">Browse by category</p>
            <h2>Fresh menu categories</h2>
          </div>
        </div>
        <div className="category-pills static">
          {categories.slice(0, 5).map((category) => (
            <Link
              key={category.id}
              to={`/menu?category=${category.id}`}
              className="category-pill active-soft"
            >
              {category.name}
            </Link>
          ))}
        </div>
      </section>

      {isLoading ? <LoadingSkeleton cardCount={2} lines={4} /> : null}
      {errorMessage ? <ErrorState description={errorMessage} onRetry={() => window.location.reload()} /> : null}

      {!isLoading && !errorMessage ? (
        <>
          <section className="section-shell">
            <div className="section-header">
              <div>
                <p className="eyebrow">Featured right now</p>
                <h2>Pickup-ready favourites</h2>
              </div>
            </div>

            {featuredProducts.length === 0 ? (
              <EmptyState
                title="No featured products yet"
                description="The customer PWA shell is ready, but there are no featured menu items available right now."
                actionLabel="Open full menu"
                actionHref="/menu"
              />
            ) : (
              <div className="product-grid">
                {featuredProducts.map((product) => (
                  <ProductCard
                    key={product.id}
                    product={product}
                    isBusy={pendingProductId === product.id}
                    onAddToCart={handleAddToCart}
                  />
                ))}
              </div>
            )}
          </section>

          {newProducts.length > 0 ? (
            <section className="section-shell">
              <div className="section-header">
                <div>
                  <p className="eyebrow">Just landed</p>
                  <h2>New on the menu</h2>
                </div>
              </div>
              <div className="product-grid">
                {newProducts.map((product) => (
                  <ProductCard
                    key={product.id}
                    product={product}
                    isBusy={pendingProductId === product.id}
                    onAddToCart={handleAddToCart}
                  />
                ))}
              </div>
            </section>
          ) : null}

          {bestsellerProducts.length > 0 ? (
            <section className="section-shell">
              <div className="section-header">
                <div>
                  <p className="eyebrow">Customer favourites</p>
                  <h2>Bestsellers</h2>
                </div>
              </div>
              <div className="product-grid">
                {bestsellerProducts.map((product) => (
                  <ProductCard
                    key={product.id}
                    product={product}
                    isBusy={pendingProductId === product.id}
                    onAddToCart={handleAddToCart}
                  />
                ))}
              </div>
            </section>
          ) : null}

          {websiteContent ? <HomeContentSections business={websiteContent.business} /> : null}
        </>
      ) : null}
    </div>
  );
}
