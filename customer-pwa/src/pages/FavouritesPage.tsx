import { FormEvent, useEffect, useMemo, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { fetchFavourites } from '../api/favourites';
import { ApiError } from '../api/client';
import { FavouriteToggle } from '../components/catalog/FavouriteToggle';
import { ProductCard } from '../components/catalog/ProductCard';
import { EmptyState } from '../components/common/EmptyState';
import { ErrorState } from '../components/common/ErrorState';
import { LoadingSkeleton } from '../components/common/LoadingSkeleton';
import { PageHeader } from '../components/common/PageHeader';
import { useCartStore } from '../stores/cartStore';
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
  const [pendingProductId, setPendingProductId] = useState<number | null>(null);
  const addItem = useCartStore((state) => state.addItem);
  const favouriteIds = useFavouriteStore((state) => state.ids);
  const hasLoadedFavourites = useFavouriteStore((state) => state.hasLoaded);
  const refreshIds = useFavouriteStore((state) => state.refreshIds);
  const navigate = useNavigate();
  const location = useLocation();

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
      navigate('/cart');
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) {
        navigate(buildLoginRedirect(location.pathname, location.search));
      } else {
        setErrorMessage(error instanceof ApiError ? error.message : 'Unable to add this favourite to your cart.');
      }
    } finally {
      setPendingProductId(null);
    }
  }

  function handleLoadMore(event: FormEvent): void {
    event.preventDefault();
    void loadFavourites(page + 1, true);
  }

  return (
    <div className="page-container">
      <PageHeader
        title="Favourites"
        description="Your saved drinks, refreshed from the server."
        showBack
        rightSlot={
          <button type="button" className="link-button" onClick={() => void loadFavourites(1)} disabled={isLoading}>
            Refresh
          </button>
        }
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
                <ProductCard
                  product={product}
                  onAddToCart={handleAddToCart}
                  isBusy={pendingProductId === product.id}
                  showFavouriteToggle={false}
                />
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
