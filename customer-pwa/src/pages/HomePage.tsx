import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import {
  fetchBestsellerProducts,
  fetchCategories,
  fetchFeaturedProducts,
  fetchNewProducts,
} from '../api/catalog';
import { fetchWebsiteContent } from '../api/content';
import { ApiError } from '../api/client';
import { FeaturedHero } from '../components/catalog/FeaturedHero';
import { ProductCard } from '../components/catalog/ProductCard';
import { ProductRail } from '../components/catalog/ProductRail';
import { HomeContentSections } from '../components/content/HomeContentSections';
import { EmptyState } from '../components/common/EmptyState';
import { ErrorState } from '../components/common/ErrorState';
import { Header } from '../components/common/Header';
import { LoadingSkeleton } from '../components/common/LoadingSkeleton';
import { Product, ProductCategory } from '../types/catalog';
import { WebsiteContent } from '../types/content';
import { useCartStore } from '../stores/cartStore';
import { useToastStore } from '../stores/toastStore';
import { buildCartDisplayFromProduct } from '../utils/cartDisplay';
import { canQuickAddProduct, getProductVariants } from '../utils/productActions';

export function HomePage() {
  const [featuredProducts, setFeaturedProducts] = useState<Product[]>([]);
  const [newProducts, setNewProducts] = useState<Product[]>([]);
  const [bestsellerProducts, setBestsellerProducts] = useState<Product[]>([]);
  const [categories, setCategories] = useState<ProductCategory[]>([]);
  const [websiteContent, setWebsiteContent] = useState<WebsiteContent | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [pendingProductId, setPendingProductId] = useState<number | null>(null);
  const addItem = useCartStore((state) => state.addItem);
  const toastSuccess = useToastStore((state) => state.success);
  const toastError = useToastStore((state) => state.error);
  const navigate = useNavigate();

  useEffect(() => {
    async function load(): Promise<void> {
      setIsLoading(true);
      setErrorMessage(null);

      try {
        const [featuredResponse, categoryResponse, newResponse, bestsellerResponse] = await Promise.all([
          fetchFeaturedProducts(),
          fetchCategories(),
          fetchNewProducts(),
          fetchBestsellerProducts(),
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
        const message = error instanceof ApiError ? error.message : 'Unable to load the cafe menu right now.';
        setErrorMessage(message);
      } finally {
        setIsLoading(false);
      }
    }

    void load();
  }, []);

  async function handleAddToCart(product: Product): Promise<void> {
    if (!canQuickAddProduct(product)) {
      navigate(`/menu/${product.id}`);
      return;
    }

    const variant = getProductVariants(product)[0];
    setPendingProductId(product.id);

    try {
      await addItem({
        product_variant_id: variant.id,
        quantity: 1,
        display: buildCartDisplayFromProduct(product, variant),
      });
      toastSuccess(`${product.name} added to cart`);
    } catch (error) {
      const message = error instanceof ApiError ? error.message : 'Unable to add the item right now.';
      toastError(message);
    } finally {
      setPendingProductId(null);
    }
  }

  return (
    <div className="page-container home-page">
      <Header />
      <FeaturedHero hero={websiteContent?.hero} businessName={websiteContent?.business.name} />

      <section className="section-shell home-categories">
        <div className="section-header">
          <div>
            <p className="eyebrow">Categories</p>
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
          <section className="section-shell">
            <div className="section-header">
              <div>
                <p className="eyebrow">Featured</p>
                <h2>Pickup-ready picks</h2>
              </div>
              <Link to="/menu" className="text-link">
                See all
              </Link>
            </div>

            {featuredProducts.length === 0 ? (
              <EmptyState
                title="No featured drinks yet"
                description="Browse the full menu to find something to order for pickup."
                actionLabel="Open menu"
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
            <ProductRail eyebrow="Just landed" title="New on the menu" seeAllHref="/menu" seeAllLabel="See all">
              {newProducts.map((product) => (
                <div key={product.id} className="product-rail-item" role="listitem">
                  <ProductCard
                    product={product}
                    layout="rail"
                    isBusy={pendingProductId === product.id}
                    onAddToCart={handleAddToCart}
                  />
                </div>
              ))}
            </ProductRail>
          ) : null}

          {bestsellerProducts.length > 0 ? (
            <ProductRail
              eyebrow="Customer favourites"
              title="Bestsellers"
              seeAllHref="/menu"
              seeAllLabel="See all"
            >
              {bestsellerProducts.map((product) => (
                <div key={product.id} className="product-rail-item" role="listitem">
                  <ProductCard
                    product={product}
                    layout="rail"
                    isBusy={pendingProductId === product.id}
                    onAddToCart={handleAddToCart}
                  />
                </div>
              ))}
            </ProductRail>
          ) : null}

          {websiteContent ? <HomeContentSections business={websiteContent.business} /> : null}
        </>
      ) : null}
    </div>
  );
}
