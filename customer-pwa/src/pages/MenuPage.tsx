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
import { Product, ProductCategory, ProductFlavour } from '../types/catalog';
import { groupProductsByCategory } from '../utils/menuGrouping';

const SEARCH_DEBOUNCE_MS = 300;
const MENU_PAGE_SIZE = 24;

function parseIdList(raw: string | null): number[] {
  if (!raw?.trim()) {
    return [];
  }

  return raw
    .split(',')
    .map((part) => Number(part.trim()))
    .filter((value) => Number.isInteger(value) && value > 0)
    .filter((value, index, list) => list.indexOf(value) === index);
}

function serializeIdList(ids: number[]): string {
  return ids.join(',');
}

function toggleId(ids: number[], id: number): number[] {
  return ids.includes(id) ? ids.filter((value) => value !== id) : [...ids, id];
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

  const selectedCategoryIds = useMemo(() => {
    const fromList = parseIdList(searchParams.get('categories'));
    if (fromList.length > 0) {
      return fromList;
    }

    const legacy = Number(searchParams.get('category'));
    return Number.isInteger(legacy) && legacy > 0 ? [legacy] : [];
  }, [searchParams]);

  const selectedFlavourIds = useMemo(() => {
    const fromList = parseIdList(searchParams.get('flavours'));
    if (fromList.length > 0) {
      return fromList;
    }

    const legacy = Number(searchParams.get('flavour'));
    return Number.isInteger(legacy) && legacy > 0 ? [legacy] : [];
  }, [searchParams]);

  const activeSearch = useMemo(() => (searchParams.get('q') ?? '').trim(), [searchParams]);
  const [searchInput, setSearchInput] = useState(activeSearch);

  const selectedCategories = useMemo(
    () => categories.filter((category) => selectedCategoryIds.includes(category.id)),
    [categories, selectedCategoryIds],
  );
  const selectedFlavours = useMemo(
    () => flavours.filter((flavour) => selectedFlavourIds.includes(flavour.id)),
    [flavours, selectedFlavourIds],
  );

  const productGroups = useMemo(
    () => groupProductsByCategory(products, categories),
    [products, categories],
  );

  const facetFilterCount = selectedCategoryIds.length + selectedFlavourIds.length;
  const hasActiveFilters = facetFilterCount > 0 || Boolean(activeSearch);

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
            categoryIds: selectedCategoryIds,
            flavourIds: selectedFlavourIds,
            perPage: MENU_PAGE_SIZE,
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
  }, [selectedCategoryIds, selectedFlavourIds, activeSearch, isBootstrapping]);

  function updateFilterParams(mutator: (params: URLSearchParams) => void): void {
    const nextParams = new URLSearchParams(searchParams);
    mutator(nextParams);
    nextParams.delete('category');
    nextParams.delete('flavour');
    setSearchParams(nextParams, { replace: true });
  }

  function handleToggleCategory(categoryId: number | null): void {
    updateFilterParams((params) => {
      if (categoryId === null) {
        params.delete('categories');
        return;
      }

      const nextIds = toggleId(selectedCategoryIds, categoryId);

      if (nextIds.length === 0) {
        params.delete('categories');
      } else {
        params.set('categories', serializeIdList(nextIds));
      }
    });
  }

  function handleToggleFlavour(flavourId: number | null): void {
    updateFilterParams((params) => {
      if (flavourId === null) {
        params.delete('flavours');
        return;
      }

      const nextIds = toggleId(selectedFlavourIds, flavourId);

      if (nextIds.length === 0) {
        params.delete('flavours');
      } else {
        params.set('flavours', serializeIdList(nextIds));
      }
    });
  }

  function handleClearFacetFilters(): void {
    updateFilterParams((params) => {
      params.delete('categories');
      params.delete('flavours');
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

  function removeCategory(categoryId: number): void {
    handleToggleCategory(categoryId);
  }

  function removeFlavour(flavourId: number): void {
    handleToggleFlavour(flavourId);
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
      <h1 className="visually-hidden">Menu</h1>

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

        <section className="menu-filter-section">
          <p className="menu-filter-label">Categories</p>
          <div className="menu-filter-rail">
            <CategoryPills
              categories={categories}
              selectedCategoryIds={selectedCategoryIds}
              onToggle={handleToggleCategory}
            />
          </div>
        </section>

        {flavours.length > 0 ? (
          <section className="menu-filter-section">
            <p className="menu-filter-label">Flavours</p>
            <div className="menu-filter-rail">
              <FlavourPills
                flavours={flavours}
                selectedFlavourIds={selectedFlavourIds}
                onToggle={handleToggleFlavour}
              />
            </div>
          </section>
        ) : null}

        {facetFilterCount > 0 ? (
          <div className="menu-active-summary" aria-label="Selected filters">
            <div className="menu-active-summary-rail">
              {selectedCategories.map((category) => (
                <button
                  type="button"
                  key={`category-${category.id}`}
                  className="menu-active-chip"
                  onClick={() => removeCategory(category.id)}
                  aria-label={`Remove ${category.name} filter`}
                >
                  {category.name}
                  <i className="bi bi-x" aria-hidden="true"></i>
                </button>
              ))}
              {selectedFlavours.map((flavour) => (
                <button
                  type="button"
                  key={`flavour-${flavour.id}`}
                  className="menu-active-chip"
                  onClick={() => removeFlavour(flavour.id)}
                  aria-label={`Remove ${flavour.name} filter`}
                >
                  {flavour.name}
                  <i className="bi bi-x" aria-hidden="true"></i>
                </button>
              ))}
            </div>
            <button type="button" className="link-button menu-clear-filters" onClick={handleClearFacetFilters}>
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

      {!isBootstrapping && !isLoadingProducts && !errorMessage && productGroups.length > 0 ? (
        <div className="menu-product-groups menu-results motion-enter">
          {productGroups.map((group) => (
            <section
              key={group.categoryId ?? 'uncategorized'}
              className="menu-category-group"
              aria-labelledby={`menu-category-${group.categoryId ?? 'other'}`}
            >
              <h2 className="menu-category-heading" id={`menu-category-${group.categoryId ?? 'other'}`}>
                {group.categoryName}
              </h2>
              <div className="product-grid">
                {group.products.map((product) => (
                  <ProductCard key={product.id} product={product} />
                ))}
              </div>
            </section>
          ))}
        </div>
      ) : null}
    </div>
  );
}
