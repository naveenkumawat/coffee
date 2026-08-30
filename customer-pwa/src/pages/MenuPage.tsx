import { useEffect, useMemo, useRef, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { buildProductQuery, fetchCategories, fetchFlavours, fetchProducts } from '../api/catalog';
import { ApiError } from '../api/client';
import { CategoryPills } from '../components/catalog/CategoryPills';
import { FlavourPills } from '../components/catalog/FlavourPills';
import { ProductCard } from '../components/catalog/ProductCard';
import { EmptyState } from '../components/common/EmptyState';
import { ErrorState } from '../components/common/ErrorState';
import { LoadingSkeleton } from '../components/common/LoadingSkeleton';
import { PageHeader } from '../components/common/PageHeader';
import { Product, ProductCategory, ProductFlavour } from '../types/catalog';

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
  const [resultTotal, setResultTotal] = useState<number | null>(null);
  const [categories, setCategories] = useState<ProductCategory[]>([]);
  const [flavours, setFlavours] = useState<ProductFlavour[]>([]);
  const [isBootstrapping, setIsBootstrapping] = useState(true);
  const [isLoadingProducts, setIsLoadingProducts] = useState(true);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const productRequestId = useRef(0);

  const activeCategoryId = useMemo(() => parsePositiveInt(searchParams.get('category')), [searchParams]);
  const activeFlavourId = useMemo(() => parsePositiveInt(searchParams.get('flavour')), [searchParams]);
  const activeSearch = useMemo(() => (searchParams.get('q') ?? '').trim(), [searchParams]);
  const [searchInput, setSearchInput] = useState(activeSearch);

  const activeCategory = useMemo(
    () => categories.find((category) => category.id === activeCategoryId) ?? null,
    [activeCategoryId, categories],
  );
  const activeFlavour = useMemo(
    () => flavours.find((flavour) => flavour.id === activeFlavourId) ?? null,
    [activeFlavourId, flavours],
  );

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
        const [categoryResponse, flavourResponse] = await Promise.all([fetchCategories(), fetchFlavours()]);

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
        const response = await fetchProducts(
          buildProductQuery({
            search: activeSearch,
            categoryId: activeCategoryId,
            flavourId: activeFlavourId,
            perPage: 24,
          }),
        );

        if (cancelled || requestId !== productRequestId.current) {
          return;
        }

        setProducts(response.data);
        setResultTotal(response.meta?.pagination?.total ?? response.data.length);
      } catch (error) {
        if (cancelled || requestId !== productRequestId.current) {
          return;
        }

        setErrorMessage(error instanceof ApiError ? error.message : 'Unable to load the menu.');
        setProducts([]);
        setResultTotal(0);
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

  function handleClearSearch(): void {
    setSearchInput('');
    updateFilterParams((params) => {
      params.delete('q');
    });
  }

  const emptyDescription = hasActiveFilters
    ? 'No drinks match these filters. Clear them or try another search.'
    : 'No drinks are available on the menu right now.';

  const resultsLabel =
    resultTotal === null
      ? 'Loading drinks…'
      : resultTotal === 1
        ? '1 drink'
        : `${resultTotal} drinks`;

  return (
    <div className="page-container menu-page">
      <PageHeader title="Menu" description="Find a drink and order for pickup." />

      <div className="menu-discovery">
        <label className="menu-search-field">
          <span className="visually-hidden">Search products</span>
          <i className="bi bi-search" aria-hidden="true"></i>
          <input
            type="search"
            className="coffee-input"
            name="q"
            value={searchInput}
            placeholder="Search drinks…"
            autoComplete="off"
            inputMode="search"
            onChange={(event) => setSearchInput(event.target.value)}
          />
          {searchInput ? (
            <button type="button" className="menu-search-clear" onClick={handleClearSearch} aria-label="Clear search">
              <i className="bi bi-x-lg" aria-hidden="true"></i>
            </button>
          ) : null}
        </label>

        <div className="menu-filter-block">
          <p className="menu-filter-label">Categories</p>
          <CategoryPills categories={categories} activeCategoryId={activeCategoryId} onSelect={handleSelectCategory} />
        </div>

        <div className="menu-filter-block">
          <p className="menu-filter-label">Flavours</p>
          <FlavourPills flavours={flavours} activeFlavourId={activeFlavourId} onSelect={handleSelectFlavour} />
        </div>

        {hasActiveFilters ? (
          <div className="menu-active-filters" aria-label="Active filters">
            {activeSearch ? (
              <button type="button" className="filter-chip" onClick={handleClearSearch}>
                “{activeSearch}”
                <i className="bi bi-x" aria-hidden="true"></i>
              </button>
            ) : null}
            {activeCategory ? (
              <button type="button" className="filter-chip" onClick={() => handleSelectCategory(null)}>
                {activeCategory.name}
                <i className="bi bi-x" aria-hidden="true"></i>
              </button>
            ) : null}
            {activeFlavour ? (
              <button type="button" className="filter-chip" onClick={() => handleSelectFlavour(null)}>
                {activeFlavour.name}
                <i className="bi bi-x" aria-hidden="true"></i>
              </button>
            ) : null}
            <button type="button" className="link-button" onClick={handleClearFilters}>
              Clear all
            </button>
          </div>
        ) : null}

        <div className="menu-results-meta" aria-live="polite">
          <strong>{isLoadingProducts ? 'Updating…' : resultsLabel}</strong>
        </div>
      </div>

      {isBootstrapping && isLoadingProducts ? <LoadingSkeleton cardCount={3} lines={3} variant="list" /> : null}

      {!isBootstrapping && isLoadingProducts ? (
        <div className="menu-results is-loading" aria-busy="true">
          <LoadingSkeleton cardCount={3} lines={3} variant="list" />
        </div>
      ) : null}

      {!isBootstrapping && !isLoadingProducts && errorMessage ? (
        <ErrorState description={errorMessage} onRetry={() => window.location.reload()} />
      ) : null}

      {!isBootstrapping && !isLoadingProducts && !errorMessage && products.length === 0 ? (
        <EmptyState
          title="No drinks found"
          description={emptyDescription}
          actionLabel={hasActiveFilters ? 'Clear filters' : 'Back home'}
          actionHref={hasActiveFilters ? undefined : '/'}
          onAction={hasActiveFilters ? handleClearFilters : undefined}
        />
      ) : null}

      {!isBootstrapping && !isLoadingProducts && !errorMessage && products.length > 0 ? (
        <div className="product-grid menu-results motion-enter">
          {products.map((product) => (
            <ProductCard key={product.id} product={product} />
          ))}
        </div>
      ) : null}
    </div>
  );
}
