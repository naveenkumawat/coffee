import { useEffect, useMemo, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { fetchCategories, fetchFlavours, fetchMenuCatalogue } from '../api/catalog';
import { ApiError } from '../api/client';
import { fetchHome } from '../api/home';
import { LandingCampaignSurface } from '../components/campaigns/LandingCampaignSurface';
import { CategoryPills } from '../components/catalog/CategoryPills';
import { FlavourPills } from '../components/catalog/FlavourPills';
import { HomeProductSection } from '../components/catalog/HomeProductSection';
import { ProductCard } from '../components/catalog/ProductCard';
import { EmptyState } from '../components/common/EmptyState';
import { ErrorState } from '../components/common/ErrorState';
import { LoadingSkeleton } from '../components/common/LoadingSkeleton';
import { Product, ProductCategory, ProductFlavour } from '../types/catalog';
import { HomeCampaigns, HomeSection } from '../types/home';
import { filterMenuProducts } from '../utils/menuFilters';
import { groupProductsByCategory } from '../utils/menuGrouping';
import { getOrCreateCampaignSessionKey } from '../utils/campaignSession';
import { getOrCreateVisitorId } from '../utils/visitorId';
import { trackBehaviour } from '../tracking/behaviourTracker';

const SEARCH_DEBOUNCE_MS = 300;

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
  const [catalogue, setCatalogue] = useState<Product[]>([]);
  const [categories, setCategories] = useState<ProductCategory[]>([]);
  const [flavours, setFlavours] = useState<ProductFlavour[]>([]);
  const [landingSections, setLandingSections] = useState<HomeSection[]>([]);
  const [landingCampaigns, setLandingCampaigns] = useState<HomeCampaigns>({ banner: null, inline: null });
  const [isLoading, setIsLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

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

  const filteredProducts = useMemo(
    () =>
      filterMenuProducts(catalogue, {
        search: activeSearch,
        categoryIds: selectedCategoryIds,
        flavourIds: selectedFlavourIds,
      }),
    [catalogue, activeSearch, selectedCategoryIds, selectedFlavourIds],
  );

  const productGroups = useMemo(
    () => groupProductsByCategory(filteredProducts, categories),
    [filteredProducts, categories],
  );

  const facetFilterCount = selectedCategoryIds.length + selectedFlavourIds.length;
  const hasActiveFilters = facetFilterCount > 0 || Boolean(activeSearch);

  useEffect(() => {
    setSearchInput(activeSearch);
  }, [activeSearch]);

  useEffect(() => {
    if (!activeSearch) {
      return;
    }

    const handle = window.setTimeout(() => {
      trackBehaviour({
        event_type: 'search_performed',
        metadata: {
          query: activeSearch,
          result_count: filteredProducts.length,
        },
        dedupe_key: `search:${activeSearch.toLowerCase()}`,
      });
    }, SEARCH_DEBOUNCE_MS);

    return () => window.clearTimeout(handle);
  }, [activeSearch, filteredProducts.length]);

  useEffect(() => {
    if (selectedCategoryIds.length === 0) {
      return;
    }

    for (const categoryId of selectedCategoryIds) {
      trackBehaviour({
        event_type: 'category_viewed',
        product_category_id: categoryId,
        metadata: { source: 'menu_filter' },
        dedupe_key: `category_viewed:${categoryId}`,
      });
    }
  }, [selectedCategoryIds]);

  useEffect(() => {
    let cancelled = false;

    async function loadCatalogue(): Promise<void> {
      setIsLoading(true);
      setErrorMessage(null);

      try {
        const [categoryResponse, flavourResponse, products, homeResponse] = await Promise.all([
          fetchCategories(),
          fetchFlavours(),
          fetchMenuCatalogue(),
          fetchHome({
            placement: 'menu',
            visitor_key: getOrCreateVisitorId(),
            session_key: getOrCreateCampaignSessionKey(),
          }).catch(() => null),
        ]);

        if (cancelled) {
          return;
        }

        setCategories(categoryResponse.data);
        setFlavours(flavourResponse.data);
        setCatalogue(products);
        setLandingSections(homeResponse?.data.sections ?? []);
        setLandingCampaigns(homeResponse?.data.campaigns ?? { banner: null, inline: null });
      } catch (error) {
        if (!cancelled) {
          setErrorMessage(error instanceof ApiError ? error.message : 'Unable to load the menu.');
          setCatalogue([]);
        }
      } finally {
        if (!cancelled) {
          setIsLoading(false);
        }
      }
    }

    void loadCatalogue();

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

  async function handleRetry(): Promise<void> {
    setIsLoading(true);
    setErrorMessage(null);

    try {
      const [categoryResponse, flavourResponse, products] = await Promise.all([
        fetchCategories(),
        fetchFlavours(),
        fetchMenuCatalogue(true),
      ]);

      setCategories(categoryResponse.data);
      setFlavours(flavourResponse.data);
      setCatalogue(products);
    } catch (error) {
      setErrorMessage(error instanceof ApiError ? error.message : 'Unable to load the menu.');
      setCatalogue([]);
    } finally {
      setIsLoading(false);
    }
  }

  const emptyDescription = hasActiveFilters
    ? 'No drinks match these filters. Clear them or try another search.'
    : 'No drinks are available on the menu right now.';

  const resultsLabel =
    filteredProducts.length === 1 ? '1 drink' : `${filteredProducts.length} drinks`;

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
          <strong>{isLoading ? 'Loading drinks…' : resultsLabel}</strong>
        </div>
      </div>

      {!isLoading && !errorMessage && !hasActiveFilters ? (
        <>
          <LandingCampaignSurface campaign={landingCampaigns.banner} surface="banner" placement="menu" />
          {landingSections.map((section) => (
            <HomeProductSection key={section.id} section={section} placement="menu_rail" />
          ))}
          <LandingCampaignSurface campaign={landingCampaigns.inline} surface="inline" placement="menu" />
        </>
      ) : null}

      {isLoading ? <LoadingSkeleton cardCount={3} lines={3} variant="list" /> : null}

      {!isLoading && errorMessage ? (
        <ErrorState description={errorMessage} onRetry={() => void handleRetry()} />
      ) : null}

      {!isLoading && !errorMessage && filteredProducts.length === 0 ? (
        <EmptyState
          title="No drinks found"
          description={emptyDescription}
          actionLabel={hasActiveFilters ? 'Clear filters' : 'Back home'}
          actionHref={hasActiveFilters ? undefined : '/'}
          onAction={hasActiveFilters ? handleClearFilters : undefined}
        />
      ) : null}

      {!isLoading && !errorMessage && productGroups.length > 0 ? (
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
