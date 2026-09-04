import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { fetchCategories, fetchFlavours, fetchMenuCatalogue } from '../api/catalog';
import { ApiError } from '../api/client';
import { DiningSession, addDiningDraft, fetchDiningSession } from '../api/dining';
import { CategoryPills } from '../components/catalog/CategoryPills';
import { FlavourPills } from '../components/catalog/FlavourPills';
import { ProductCard } from '../components/catalog/ProductCard';
import { ProductOrderHandler } from '../components/catalog/ProductOrderControl';
import { EmptyState } from '../components/common/EmptyState';
import { ErrorState } from '../components/common/ErrorState';
import { LoadingSkeleton } from '../components/common/LoadingSkeleton';
import { PageHeader } from '../components/common/PageHeader';
import { StickyActionBar } from '../components/common/StickyActionBar';
import { useToastStore } from '../stores/toastStore';
import { Product, ProductCategory, ProductFlavour } from '../types/catalog';
import { formatCurrency } from '../utils/format';
import { filterMenuProducts } from '../utils/menuFilters';
import { groupProductsByCategory } from '../utils/menuGrouping';
import {
  clearOrderingContext,
  diningDraftItemCount,
  diningSessionPath,
  isDiningSessionTerminal,
  writeOrderingContext,
} from '../utils/orderingContext';

const SEARCH_DEBOUNCE_MS = 300;

function toggleId(ids: number[], id: number): number[] {
  return ids.includes(id) ? ids.filter((value) => value !== id) : [...ids, id];
}

export function DiningMenuPage() {
  const { sessionId = '' } = useParams();
  const navigate = useNavigate();
  const toastSuccess = useToastStore((state) => state.success);
  const toastError = useToastStore((state) => state.error);

  const [session, setSession] = useState<DiningSession | null>(null);
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
    writeOrderingContext({ type: 'dining', diningSessionId: sessionId });
  }, [sessionId]);

  useEffect(() => {
    let cancelled = false;

    async function load(): Promise<void> {
      setIsLoading(true);
      setErrorMessage(null);

      try {
        const [sessionResponse, categoryResponse, flavourResponse, products] = await Promise.all([
          fetchDiningSession(sessionId),
          fetchCategories(),
          fetchFlavours(),
          fetchMenuCatalogue(),
        ]);

        if (cancelled) {
          return;
        }

        if (isDiningSessionTerminal(sessionResponse.data)) {
          clearOrderingContext();
          navigate('/dining', { replace: true });

          return;
        }

        if (!(sessionResponse.data.capabilities?.can_add_rounds ?? sessionResponse.data.status === 'open')) {
          navigate(diningSessionPath(sessionId), { replace: true });

          return;
        }

        setSession(sessionResponse.data);
        writeOrderingContext({
          type: 'dining',
          diningSessionId: sessionId,
          tableLabel: sessionResponse.data.table.label,
          draftItemCount: diningDraftItemCount(sessionResponse.data.drafts),
        });
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
  }, [navigate, sessionId]);

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

    return session.drafts.reduce((sum, draft) => sum + Number(draft.line_total ?? 0), 0).toFixed(2);
  }, [session]);

  const orderHandler = useMemo<ProductOrderHandler>(
    () => ({
      add: async (payload) => {
        try {
          const response = await addDiningDraft(sessionId, payload);
          setSession(response.data);
          writeOrderingContext({
            type: 'dining',
            diningSessionId: sessionId,
            tableLabel: response.data.table.label,
            draftItemCount: diningDraftItemCount(response.data.drafts),
          });
          toastSuccess('Added to your next round');
        } catch (error) {
          toastError(error instanceof ApiError ? error.message : 'Unable to add item.');
          throw error;
        }
      },
    }),
    [sessionId, toastError, toastSuccess],
  );

  const handleRetry = useCallback(async (): Promise<void> => {
    setIsLoading(true);
    setErrorMessage(null);

    try {
      const [sessionResponse, categoryResponse, flavourResponse, products] = await Promise.all([
        fetchDiningSession(sessionId),
        fetchCategories(),
        fetchFlavours(),
        fetchMenuCatalogue(true),
      ]);

      setSession(sessionResponse.data);
      writeOrderingContext({
        type: 'dining',
        diningSessionId: sessionId,
        tableLabel: sessionResponse.data.table.label,
        draftItemCount: diningDraftItemCount(sessionResponse.data.drafts),
      });
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
      <div className="page-container dining-menu-page">
        <PageHeader title="Add items" showBack />
        <LoadingSkeleton cardCount={3} lines={3} variant="list" />
      </div>
    );
  }

  if (errorMessage) {
    return (
      <div className="page-container dining-menu-page">
        <PageHeader title="Add items" showBack />
        <ErrorState description={errorMessage} onRetry={() => void handleRetry()} />
      </div>
    );
  }

  return (
    <div className="page-container dining-menu-page menu-page has-sticky-cta is-sticky-stack">
      <PageHeader
        title="Add items"
        description={session ? `Table ${session.table.label}` : 'Dining'}
        showBack
      />
      {session ? (
        <p className="dining-menu-context" aria-live="polite">
          Ordering for <strong>Table {session.table.label}</strong>
          <span className="muted"> · Adds to your next round</span>
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
            placeholder="Search drinks & food…"
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
          title="No items found"
          description={
            hasActiveFilters
              ? 'No menu items match these filters.'
              : 'No menu items are available right now.'
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
              aria-labelledby={`dining-menu-category-${group.categoryId ?? 'other'}`}
            >
              <h2
                className="menu-category-heading"
                id={`dining-menu-category-${group.categoryId ?? 'other'}`}
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
                    sheetCtaLabel="Add to round"
                  />
                ))}
              </div>
            </section>
          ))}
        </div>
      )}

      <StickyActionBar
        eyebrow={session ? `Table ${session.table.label}` : 'Next round'}
        title={
          draftCount > 0
            ? `${draftCount} item${draftCount === 1 ? '' : 's'} in next round`
            : 'No items in next round'
        }
        value={formatCurrency(draftTotal)}
      >
        <Link
          to={diningSessionPath(sessionId)}
          className="btn btn-primary btn-lg rounded-pill w-100"
        >
          {draftCount > 0 ? 'View round' : 'Back to table'}
        </Link>
      </StickyActionBar>
    </div>
  );
}
