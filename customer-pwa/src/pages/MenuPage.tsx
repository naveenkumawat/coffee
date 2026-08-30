import { useEffect, useMemo, useState } from 'react';
import { useLocation, useNavigate, useSearchParams } from 'react-router-dom';
import { fetchCategories, fetchProducts } from '../api/catalog';
import { ApiError } from '../api/client';
import { CategoryPills } from '../components/catalog/CategoryPills';
import { ProductCard } from '../components/catalog/ProductCard';
import { EmptyState } from '../components/common/EmptyState';
import { ErrorState } from '../components/common/ErrorState';
import { LoadingSkeleton } from '../components/common/LoadingSkeleton';
import { PageHeader } from '../components/common/PageHeader';
import { useCartStore } from '../stores/cartStore';
import { Product, ProductCategory } from '../types/catalog';
import { buildLoginRedirect } from '../utils/navigation';

export function MenuPage() {
  const [searchParams, setSearchParams] = useSearchParams();
  const [products, setProducts] = useState<Product[]>([]);
  const [categories, setCategories] = useState<ProductCategory[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [pendingProductId, setPendingProductId] = useState<number | null>(null);
  const addItem = useCartStore((state) => state.addItem);
  const navigate = useNavigate();
  const location = useLocation();

  const activeCategoryId = useMemo(() => {
    const categoryId = searchParams.get('category');

    return categoryId ? Number(categoryId) : null;
  }, [searchParams]);

  useEffect(() => {
    async function load(): Promise<void> {
      setIsLoading(true);
      setErrorMessage(null);

      try {
        const query = new URLSearchParams();

        if (activeCategoryId) {
          query.set('product_category_id', String(activeCategoryId));
        }

        const [productResponse, categoryResponse] = await Promise.all([
          fetchProducts(query.toString()),
          fetchCategories()
        ]);

        setProducts(productResponse.data);
        setCategories(categoryResponse.data);
      } catch (error) {
        setErrorMessage(error instanceof ApiError ? error.message : 'Unable to load the menu.');
      } finally {
        setIsLoading(false);
      }
    }

    void load();
  }, [activeCategoryId]);

  function handleSelectCategory(categoryId: number | null): void {
    setSearchParams(categoryId ? { category: String(categoryId) } : {});
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

  return (
    <div className="page-container">
      <PageHeader title="Menu" description="Live catalog from the Laravel API." />
      <CategoryPills categories={categories} activeCategoryId={activeCategoryId} onSelect={handleSelectCategory} />

      {isLoading ? <LoadingSkeleton cardCount={3} lines={4} /> : null}
      {errorMessage ? <ErrorState description={errorMessage} onRetry={() => window.location.reload()} /> : null}
      {!isLoading && !errorMessage && products.length === 0 ? (
        <EmptyState
          title="No products available"
          description="This category doesn’t have any currently available variants."
          actionLabel="Show all products"
          actionHref="/menu"
        />
      ) : null}
      {!isLoading && !errorMessage ? (
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
    </div>
  );
}
