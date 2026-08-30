import { useEffect, useMemo, useRef, useState } from 'react';
import { useLocation, useNavigate, useSearchParams } from 'react-router-dom';
import { buildProductQuery, fetchCategories, fetchFlavours, fetchProducts } from '../api/catalog';
import { ApiError } from '../api/client';
import { CategoryPills } from '../components/catalog/CategoryPills';
import { FlavourPills } from '../components/catalog/FlavourPills';
import { ProductCard } from '../components/catalog/ProductCard';
import { EmptyState } from '../components/common/EmptyState';
import { ErrorState } from '../components/common/ErrorState';
import { LoadingSkeleton } from '../components/common/LoadingSkeleton';
import { PageHeader } from '../components/common/PageHeader';
import { useCartStore } from '../stores/cartStore';
import { Product, ProductCategory, ProductFlavour } from '../types/catalog';
import { buildLoginRedirect } from '../utils/navigation';

const SEARCH_DEBOUNCE_MS = 300;

function parsePositiveInt(value: string | null): number | null {
  if (!value) {
    return null;
  }

  const parsed = Number(value);

  return Number.isInteger(parsed) && parsed > 0 ? parsed : null;
}

export function MenuPage() {
  const [searchParams, setSearchParams] = useSearchParams();
  const [products, setProducts] = useState<Product[]>([]);
  const [categories, setCategories] = useState<ProductCategory[]>([]);
  const [flavours, setFlavours] = useState<ProductFlavour[]>([]);
  const [isBootstrapping, setIsBootstrapping] = useState(true);
  const [isLoadingProducts, setIsLoadingProducts] = useState(true);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [pendingProductId, setPendingProductId] = useState<number | null>(null);
  const addItem = useCartStore((state) => state.addItem);
  const navigate = useNavigate();
  const location = useLocation();
  const productRequestId = useRef(0);

  const activeCategoryId = useMemo(() => parsePositiveInt(searchParams.get('category')), [searchParams]);
  const activeFlavourId = useMemo(() => parsePositiveInt(searchParams.get('flavour')), [searchParams]);
  const activeSearch = useMemo(() => (searchParams.get('q') ?? '').trim(), [searchParams]);
  const [searchInput, setSearchInput] = useState(activeSearch);

  const hasActiveFilters = Boolean(activeCategoryId || activeFlavourId || activeSearch);

  useEffect(() => {
    setSearchInput(activeSearch);
  }, [activeSearch]);

  useEffect(() => {
    let cancelled = false;

    async function loadFilters(): Promise<void> {
      setIsBootstrapping(true);
      setErrorMessage(null);

      try {
        const [categoryResponse, flavourResponse] = await Promise.all([
          fetchCategories(),
          fetchFlavours()
        ]);

        if (cancelled) {
          return;
        }

        setCategories(categoryResponse.data);
        setFlavours(flavourResponse.data);
      } catch (error) {
        if (!cancelled) {
          setErrorMessage(error instanceof ApiError ? error.message : 'Unable to load menu filters.');
        }
      } finally {
        if (!cancelled) {
          setIsBootstrapping(false);
        }
      }
    }

    void loadFilters();

    return () => {
      cancelled = true;
    };
  }, []);

  useEffect(() => {
    const handle = window.setTimeout(() => {
      const nextValue = searchInput.trim();

      if (nextValue === activeSearch) {
        return;
      }

      const nextParams = new URLSearchParams(searchParams);

      if (nextValue) {
        nextParams.set('q', nextValue);
      } else {
        nextParams.delete('q');
      }

      setSearchParams(nextParams, { replace: true });
    }, SEARCH_DEBOUNCE_MS);

    return () => window.clearTimeout(handle);
  }, [activeSearch, searchInput, searchParams, setSearchParams]);

  useEffect(() => {
    if (isBootstrapping) {
      return;
    }

    const requestId = ++productRequestId.current;
    let cancelled = false;

    async function loadProducts(): Promise<void> {
      setIsLoadingProducts(true);
      setErrorMessage(null);

      try {
        const response = await fetchProducts(buildProductQuery({
          search: activeSearch,
          categoryId: activeCategoryId,
          flavourId: activeFlavourId,
          perPage: 24
        }));

        if (cancelled || requestId !== productRequestId.current) {
          return;
        }

        setProducts(response.data);
      } catch (error) {
        if (cancelled || requestId !== productRequestId.current) {
          return;
        }

        setErrorMessage(error instanceof ApiError ? error.message : 'Unable to load the menu.');
        setProducts([]);
      } finally {
        if (!cancelled && requestId === productRequestId.current) {
          setIsLoadingProducts(false);
        }
      }
    }

    void loadProducts();

    return () => {
      cancelled = true;
    };
  }, [activeCategoryId, activeFlavourId, activeSearch, isBootstrapping]);

  function updateFilterParams(mutator: (params: URLSearchParams) => void): void {
    const nextParams = new URLSearchParams(searchParams);
    mutator(nextParams);
    setSearchParams(nextParams, { replace: true });
  }

  function handleSelectCategory(categoryId: number | null): void {
    updateFilterParams((params) => {
      if (categoryId) {
        params.set('category', String(categoryId));
      } else {
        params.delete('category');
      }
    });
  }

  function handleSelectFlavour(flavourId: number | null): void {
    updateFilterParams((params) => {
      if (flavourId) {
        params.set('flavour', String(flavourId));
      } else {
        params.delete('flavour');
      }
    });
  }

  function handleClearFilters(): void {
    setSearchInput('');
    setSearchParams({}, { replace: true });
  }

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
        setErrorMessage(error instanceof ApiError ? error.message : 'Unable to add this item.');
      }
    } finally {
      setPendingProductId(null);
    }
  }

  const emptyDescription = hasActiveFilters
    ? 'No products match your search and filters. Try clearing them or browsing another category.'
    : 'There are no currently available menu products right now.';

  return (
    <div className="page-container">
      <PageHeader
        title="Menu"
        description="Search and filter the live cafe catalog."
        rightSlot={
          hasActiveFilters ? (
            <button type="button" className="link-button" onClick={handleClearFilters}>
              Clear filters
            </button>
          ) : null
        }
      />

      <div className="menu-discovery">
        <label className="menu-search-field">
          <span className="visually-hidden">Search products</span>
          <i className="bi bi-search" aria-hidden="true"></i>
          <input
            type="search"
            className="coffee-input"
            name="q"
            value={searchInput}
            placeholder="Search drinks, ingredients..."
            autoComplete="off"
            inputMode="search"
            onChange={(event) => setSearchInput(event.target.value)}
          />
        </label>

        <div className="menu-filter-block">
          <p className="menu-filter-label">Categories</p>
          <CategoryPills categories={categories} activeCategoryId={activeCategoryId} onSelect={handleSelectCategory} />
        </div>

        <div className="menu-filter-block">
          <p className="menu-filter-label">Flavours</p>
          <FlavourPills flavours={flavours} activeFlavourId={activeFlavourId} onSelect={handleSelectFlavour} />
        </div>
      </div>

      {isBootstrapping || isLoadingProducts ? <LoadingSkeleton cardCount={3} lines={4} /> : null}
      {!isBootstrapping && !isLoadingProducts && errorMessage ? (
        <ErrorState description={errorMessage} onRetry={() => window.location.reload()} />
      ) : null}
      {!isBootstrapping && !isLoadingProducts && !errorMessage && products.length === 0 ? (
        <EmptyState
          title="No products found"
          description={emptyDescription}
          actionLabel={hasActiveFilters ? 'Clear filters' : 'Back home'}
          actionHref={hasActiveFilters ? '/menu' : '/'}
        />
      ) : null}
      {!isBootstrapping && !isLoadingProducts && !errorMessage && products.length > 0 ? (
        <div className="product-grid">
          {products.map((product) => (
            <ProductCard
              key={product.id}
              product={product}
              isBusy={pendingProductId === product.id}
              onAddToCart={handleAddToCart}
            />
          ))}
        </div>
      ) : null}

      {hasActiveFilters ? (
        <div className="page-note">
          <span>Filters are synced to the URL for sharing.</span>
          <button type="button" className="link-button" onClick={handleClearFilters}>
            Reset menu
          </button>
        </div>
      ) : null}
    </div>
  );
}
