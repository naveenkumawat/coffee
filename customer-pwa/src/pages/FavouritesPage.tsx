import { FormEvent, useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { fetchFavourites } from '../api/favourites';
import { ApiError } from '../api/client';
import { FavouriteToggle } from '../components/catalog/FavouriteToggle';
import { ProductCard } from '../components/catalog/ProductCard';
import { EmptyState } from '../components/common/EmptyState';
import { ErrorState } from '../components/common/ErrorState';
import { LoadingSkeleton } from '../components/common/LoadingSkeleton';
import { PageHeader } from '../components/common/PageHeader';
import { useFavouriteStore } from '../stores/favouriteStore';
import { Product } from '../types/catalog';
import { buildLoginRedirect } from '../utils/navigation';

export function FavouritesPage() {
  const [products, setProducts] = useState<Product[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const favouriteIds = useFavouriteStore((state) => state.ids);
  const hasLoadedFavourites = useFavouriteStore((state) => state.hasLoaded);
  const refreshIds = useFavouriteStore((state) => state.refreshIds);
  const navigate = useNavigate();

  const visibleProducts = useMemo(() => {
    if (!hasLoadedFavourites) {
      return products;
    }

    return products.filter((product) => favouriteIds.includes(product.id));
  }, [favouriteIds, hasLoadedFavourites, products]);

  async function loadFavourites(nextPage = 1, append = false): Promise<void> {
    if (append) {
      setIsLoadingMore(true);
    } else {
      setIsLoading(true);
      setErrorMessage(null);
    }

    try {
      const response = await fetchFavourites(nextPage);
      setProducts((current) => (append ? [...current, ...response.data] : response.data));
      setPage(response.meta?.pagination?.current_page ?? nextPage);
      setLastPage(response.meta?.pagination?.last_page ?? 1);
      await refreshIds();
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) {
        navigate(buildLoginRedirect('/favourites'), { replace: true });
        return;
      }

      setErrorMessage(error instanceof ApiError ? error.message : 'Unable to load your favourites.');
      if (!append) {
        setProducts([]);
      }
    } finally {
      setIsLoading(false);
      setIsLoadingMore(false);
    }
  }

  useEffect(() => {
    void loadFavourites(1);
  }, []);

  function handleLoadMore(event: FormEvent): void {
    event.preventDefault();
    void loadFavourites(page + 1, true);
  }

  return (
    <div className="page-container">
      <PageHeader
        title="Favourites"
        description="Your saved drinks for quicker ordering."
        showBack
      />

      {isLoading ? <LoadingSkeleton cardCount={3} lines={3} /> : null}
      {!isLoading && errorMessage ? <ErrorState description={errorMessage} onRetry={() => void loadFavourites(1)} /> : null}
      {!isLoading && !errorMessage && visibleProducts.length === 0 ? (
        <EmptyState
          title="No favourites yet"
          description="Tap the heart on a product to save it here for quicker reordering."
          actionLabel="Browse menu"
          actionHref="/menu"
        />
      ) : null}
      {!isLoading && !errorMessage && visibleProducts.length > 0 ? (
        <>
          <div className="product-grid">
            {visibleProducts.map((product) => (
              <div key={product.id} className="favourite-product-wrap">
                <FavouriteToggle productId={product.id} className="favourite-toggle-float" size="sm" />
                <ProductCard product={product} showFavouriteToggle={false} />
              </div>
            ))}
          </div>
          {page < lastPage ? (
            <form className="order-load-more" onSubmit={handleLoadMore}>
              <button type="submit" className="btn btn-outline-dark btn-lg rounded-pill w-100" disabled={isLoadingMore}>
                {isLoadingMore ? 'Loading...' : 'Load more favourites'}
              </button>
            </form>
          ) : null}
        </>
      ) : null}
    </div>
  );
}
