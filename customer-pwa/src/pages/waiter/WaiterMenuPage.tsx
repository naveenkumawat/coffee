import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { fetchCategories, fetchFlavours, fetchMenuCatalogue } from '../../api/catalog';
import { ApiError } from '../../api/client';
import { WaiterDiningSession, addWaiterDraft, fetchWaiterSession } from '../../api/waiterDining';
import { CategoryPills } from '../../components/catalog/CategoryPills';
import { FlavourPills } from '../../components/catalog/FlavourPills';
import { ProductCard } from '../../components/catalog/ProductCard';
import { ProductOrderHandler } from '../../components/catalog/ProductOrderControl';
import { EmptyState } from '../../components/common/EmptyState';
import { ErrorState } from '../../components/common/ErrorState';
import { LoadingSkeleton } from '../../components/common/LoadingSkeleton';
import { PageHeader } from '../../components/common/PageHeader';
import { StickyActionBar } from '../../components/common/StickyActionBar';
import { Product, ProductCategory, ProductFlavour } from '../../types/catalog';
import { formatCurrency } from '../../utils/format';
import { filterMenuProducts } from '../../utils/menuFilters';
import { groupProductsByCategory } from '../../utils/menuGrouping';
import { rememberWaiterSession } from '../../utils/waiterSession';

const SEARCH_DEBOUNCE_MS = 300;

function toggleId(ids: number[], id: number): number[] {
  return ids.includes(id) ? ids.filter((value) => value !== id) : [...ids, id];
}

export function WaiterMenuPage() {
  const { sessionId = '' } = useParams();
  const [session, setSession] = useState<WaiterDiningSession | null>(null);
  const [catalogue, setCatalogue] = useState<Product[]>([]);
  const [categories, setCategories] = useState<ProductCategory[]>([]);
  const [flavours, setFlavours] = useState<ProductFlavour[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [selectedCategoryIds, setSelectedCategoryIds] = useState<number[]>([]);
  const [selectedFlavourIds, setSelectedFlavourIds] = useState<number[]>([]);
  const [searchInput, setSearchInput] = useState('');
  const [activeSearch, setActiveSearch] = useState('');

  useEffect(() => {
    let cancelled = false;

    async function load(): Promise<void> {
      setIsLoading(true);
      setErrorMessage(null);

      try {
        const [sessionResponse, categoryResponse, flavourResponse, products] = await Promise.all([
          fetchWaiterSession(sessionId),
          fetchCategories(),
          fetchFlavours(),
          fetchMenuCatalogue(),
        ]);

        if (cancelled) {
          return;
        }

        setSession(sessionResponse.data);
        rememberWaiterSession(sessionResponse.data.id);
        setCategories(categoryResponse.data);
        setFlavours(flavourResponse.data);
        setCatalogue(products);
      } catch (error) {
        if (!cancelled) {
          setErrorMessage(error instanceof ApiError ? error.message : 'Unable to load menu.');
        }
      } finally {
        if (!cancelled) {
          setIsLoading(false);
        }
      }
    }

    void load();

    return () => {
      cancelled = true;
    };
  }, [sessionId]);

  useEffect(() => {
    const handle = window.setTimeout(() => {
      setActiveSearch(searchInput.trim());
    }, SEARCH_DEBOUNCE_MS);

    return () => window.clearTimeout(handle);
  }, [searchInput]);

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

  const draftCount = session?.drafts.reduce((sum, draft) => sum + draft.quantity, 0) ?? 0;
  const draftTotal = useMemo(() => {
    if (!session) {
      return '0.00';
    }

    return session.drafts
      .reduce((sum, draft) => sum + Number(draft.line_total ?? 0), 0)
      .toFixed(2);
  }, [session]);

  const orderHandler = useMemo<ProductOrderHandler>(
    () => ({
      add: async (payload) => {
        const response = await addWaiterDraft(sessionId, payload);
        setSession(response.data);
      },
    }),
    [sessionId],
  );

  const handleRetry = useCallback(async (): Promise<void> => {
    setIsLoading(true);
    setErrorMessage(null);

    try {
      const [sessionResponse, categoryResponse, flavourResponse, products] = await Promise.all([
        fetchWaiterSession(sessionId),
        fetchCategories(),
        fetchFlavours(),
        fetchMenuCatalogue(true),
      ]);

      setSession(sessionResponse.data);
      rememberWaiterSession(sessionResponse.data.id);
      setCategories(categoryResponse.data);
      setFlavours(flavourResponse.data);
      setCatalogue(products);
    } catch (error) {
      setErrorMessage(error instanceof ApiError ? error.message : 'Unable to load menu.');
    } finally {
      setIsLoading(false);
    }
  }, [sessionId]);

  const facetFilterCount = selectedCategoryIds.length + selectedFlavourIds.length;
  const hasActiveFilters = facetFilterCount > 0 || Boolean(activeSearch);

  if (isLoading) {
    return (
      <div className="page-container waiter-page">
        <PageHeader title="Add order" showBack />
        <LoadingSkeleton cardCount={3} lines={3} variant="list" />
      </div>
    );
  }

  if (errorMessage) {
    return (
      <div className="page-container waiter-page">
        <PageHeader title="Add order" showBack />
        <ErrorState description={errorMessage} onRetry={() => void handleRetry()} />
      </div>
    );
  }

  return (
    <div className="page-container waiter-page menu-page has-sticky-cta is-sticky-stack">
      <PageHeader
        title="Add order"
        description={session ? `Table ${session.table.label}` : undefined}
        showBack
      />
      {session ? (
        <p className="waiter-table-context" aria-live="polite">
          Ordering for <strong>{session.table.label}</strong>
        </p>
      ) : null}
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
            <button
              type="button"
              className="menu-search-clear"
              onClick={() => setSearchInput('')}
              aria-label="Clear search"
            >
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
              onToggle={(categoryId) => {
                if (categoryId === null) {
                  setSelectedCategoryIds([]);

                  return;
                }

                setSelectedCategoryIds((current) => toggleId(current, categoryId));
              }}
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
                onToggle={(flavourId) => {
                  if (flavourId === null) {
                    setSelectedFlavourIds([]);

                    return;
                  }

                  setSelectedFlavourIds((current) => toggleId(current, flavourId));
                }}
              />
            </div>
          </section>
        ) : null}
      </div>

      {filteredProducts.length === 0 ? (
        <EmptyState
          title="No drinks found"
          description={
            hasActiveFilters
              ? 'No drinks match these filters.'
              : 'No drinks are available on the menu right now.'
          }
          actionLabel={hasActiveFilters ? 'Clear filters' : undefined}
          onAction={
            hasActiveFilters
              ? () => {
                  setSelectedCategoryIds([]);
                  setSelectedFlavourIds([]);
                  setSearchInput('');
                  setActiveSearch('');
                }
              : undefined
          }
        />
      ) : (
        <div className="menu-product-groups menu-results motion-enter">
          {productGroups.map((group) => (
            <section
              key={group.categoryId ?? 'uncategorized'}
              className="menu-category-group"
              aria-labelledby={`waiter-menu-category-${group.categoryId ?? 'other'}`}
            >
              <h2
                className="menu-category-heading"
                id={`waiter-menu-category-${group.categoryId ?? 'other'}`}
              >
                {group.categoryName}
              </h2>
              <div className="product-grid">
                {group.products.map((product) => (
                  <ProductCard
                    key={product.id}
                    product={product}
                    showFavouriteToggle={false}
                    orderHandler={orderHandler}
                    sheetCtaLabel="Add to order"
                  />
                ))}
              </div>
            </section>
          ))}
        </div>
      )}

      <StickyActionBar
        eyebrow={session?.table.label ?? 'Draft'}
        title={draftCount > 0 ? 'Draft items' : 'No draft yet'}
        value={formatCurrency(draftTotal)}
        note={
          draftCount > 0
            ? `${draftCount} item${draftCount === 1 ? '' : 's'} ready to review`
            : 'Add items to this table'
        }
      >
        <Link
          to={`/waiter/sessions/${sessionId}/review`}
          className={`btn btn-primary btn-lg rounded-pill w-100 ${draftCount === 0 ? 'disabled' : ''}`}
          aria-disabled={draftCount === 0}
          onClick={(event) => {
            if (draftCount === 0) {
              event.preventDefault();
            }
          }}
        >
          Review draft
        </Link>
      </StickyActionBar>
    </div>
  );
}
