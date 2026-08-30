import { useEffect, useMemo, useState } from 'react';
import { Link, useLocation, useNavigate, useParams } from 'react-router-dom';
import { fetchProduct } from '../api/catalog';
import { ApiError } from '../api/client';
import { EmptyState } from '../components/common/EmptyState';
import { ErrorState } from '../components/common/ErrorState';
import { LoadingSkeleton } from '../components/common/LoadingSkeleton';
import { PageHeader } from '../components/common/PageHeader';
import { QuantityStepper } from '../components/common/QuantityStepper';
import { useCartStore } from '../stores/cartStore';
import { Product } from '../types/catalog';
import { formatCurrency, joinLabels } from '../utils/format';
import { pickProductImage } from '../utils/images';
import { buildLoginRedirect } from '../utils/navigation';

export function ProductDetailPage() {
  const { productId = '' } = useParams();
  const [product, setProduct] = useState<Product | null>(null);
  const [selectedVariantId, setSelectedVariantId] = useState<number | null>(null);
  const [quantity, setQuantity] = useState(1);
  const [isLoading, setIsLoading] = useState(true);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const addItem = useCartStore((state) => state.addItem);
  const navigate = useNavigate();
  const location = useLocation();

  useEffect(() => {
    async function load(): Promise<void> {
      setIsLoading(true);
      setErrorMessage(null);

      try {
        const response = await fetchProduct(productId);
        setProduct(response.data);
        setSelectedVariantId(response.data.default_variant?.id ?? response.data.variants[0]?.id ?? null);
      } catch (error) {
        setErrorMessage(error instanceof ApiError ? error.message : 'Unable to load the product detail.');
      } finally {
        setIsLoading(false);
      }
    }

    void load();
  }, [productId]);

  const selectedVariant = useMemo(
    () => product?.variants.find((variant) => variant.id === selectedVariantId) ?? product?.default_variant ?? null,
    [product, selectedVariantId]
  );

  async function handleAddToCart(): Promise<void> {
    if (!selectedVariant) {
      return;
    }

    setIsSubmitting(true);

    try {
      await addItem({
        product_variant_id: selectedVariant.id,
        quantity
      });
      navigate('/cart');
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) {
        navigate(buildLoginRedirect(location.pathname, location.search));
      } else {
        setErrorMessage(error instanceof ApiError ? error.message : 'Unable to add this item.');
      }
    } finally {
      setIsSubmitting(false);
    }
  }

  if (isLoading) {
    return (
      <div className="page-container">
        <PageHeader title="Product detail" showBack />
        <LoadingSkeleton cardCount={2} lines={5} />
      </div>
    );
  }

  if (errorMessage) {
    return (
      <div className="page-container">
        <PageHeader title="Product detail" showBack />
        <ErrorState description={errorMessage} onRetry={() => window.location.reload()} />
      </div>
    );
  }

  if (!product) {
    return (
      <div className="page-container">
        <PageHeader title="Product detail" showBack />
        <EmptyState title="Product not found" description="This menu item is no longer available." actionLabel="Back to menu" actionHref="/menu" />
      </div>
    );
  }

  const image = pickProductImage(product.name, product.image_path);

  return (
    <div className="page-container detail-page">
      <PageHeader title={product.name} description={product.category?.name ?? 'Coffee menu'} showBack />

      <section className="detail-hero">
        <div className="detail-image-wrap">
          <img src={image} alt={product.name} className="detail-image" />
        </div>
        <div className="detail-panel">
          <p className="detail-description">{product.description || product.short_description}</p>
          <p className="detail-meta">
            {joinLabels([
              product.customer_ingredient_summary,
              product.preparation_time_minutes ? `${product.preparation_time_minutes} min prep` : null
            ])}
          </p>

          <div className="variant-group">
            <h2>Choose a variant</h2>
            <div className="variant-options">
              {product.variants.map((variant) => (
                <button
                  type="button"
                  key={variant.id}
                  className={`variant-option ${selectedVariant?.id === variant.id ? 'active' : ''}`}
                  onClick={() => setSelectedVariantId(variant.id)}
                >
                  <span>{variant.name}</span>
                  <small>{variant.serving_size.label}</small>
                  <strong>{formatCurrency(variant.price)}</strong>
                </button>
              ))}
            </div>
          </div>

          <div className="detail-actions">
            <QuantityStepper value={quantity} onChange={setQuantity} />
            <button type="button" className="btn btn-primary btn-lg rounded-pill flex-grow-1" onClick={handleAddToCart} disabled={!selectedVariant || isSubmitting}>
              {isSubmitting ? 'Adding to cart...' : `Add ${formatCurrency(Number(selectedVariant?.price ?? 0) * quantity)}`}
            </button>
          </div>
        </div>
      </section>

      <div className="page-note">
        <span>Customer auth, checkout, and orders remain progressive follow-up slices.</span>
        <Link to="/cart">Open cart</Link>
      </div>
    </div>
  );
}
