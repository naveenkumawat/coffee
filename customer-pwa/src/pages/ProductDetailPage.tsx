import { useEffect, useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { fetchProduct } from '../api/catalog';
import { ApiError } from '../api/client';
import { FavouriteToggle } from '../components/catalog/FavouriteToggle';
import { ProductBadges } from '../components/catalog/ProductBadges';
import { ProductCartControl } from '../components/catalog/ProductCartControl';
import { EmptyState } from '../components/common/EmptyState';
import { ErrorState } from '../components/common/ErrorState';
import { LoadingSkeleton } from '../components/common/LoadingSkeleton';
import { PageHeader } from '../components/common/PageHeader';
import { StickyActionBar } from '../components/common/StickyActionBar';
import { useCartStore } from '../stores/cartStore';
import { Product } from '../types/catalog';
import { quantityForVariant } from '../utils/cartQuantity';
import { formatCurrency } from '../utils/format';
import { getPreferredVariant } from '../utils/productActions';
import { ProductImage } from '../components/common/ProductImage';

export function ProductDetailPage() {
  const { productId = '' } = useParams();
  const [product, setProduct] = useState<Product | null>(null);
  const [selectedVariantId, setSelectedVariantId] = useState<number | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const cart = useCartStore((state) => state.cart);

  useEffect(() => {
    async function load(): Promise<void> {
      setIsLoading(true);
      setErrorMessage(null);

      try {
        const response = await fetchProduct(productId);
        const nextProduct = response.data;
        setProduct(nextProduct);
        setSelectedVariantId(getPreferredVariant(nextProduct)?.id ?? null);
      } catch (error) {
        setErrorMessage(error instanceof ApiError ? error.message : 'Unable to load this drink.');
      } finally {
        setIsLoading(false);
      }
    }

    void load();
  }, [productId]);

  const selectedVariant = useMemo(
    () => product?.variants.find((variant) => variant.id === selectedVariantId) ?? null,
    [product, selectedVariantId],
  );

  const cartQuantity = selectedVariant ? quantityForVariant(cart, selectedVariant.id) : 0;
  const displayQuantity = Math.max(cartQuantity, 1);
  const lineTotal = selectedVariant ? Number(selectedVariant.price) * displayQuantity : 0;

  if (isLoading) {
    return (
      <div className="page-container">
        <PageHeader title="Product" showBack />
        <LoadingSkeleton cardCount={1} lines={5} variant="hero" />
      </div>
    );
  }

  if (errorMessage && !product) {
    return (
      <div className="page-container">
        <PageHeader title="Product" showBack />
        <ErrorState description={errorMessage} onRetry={() => window.location.reload()} />
      </div>
    );
  }

  if (!product) {
    return (
      <div className="page-container">
        <PageHeader title="Product" showBack />
        <EmptyState
          title="Product not found"
          description="This menu item is no longer available."
          actionLabel="Back to menu"
          actionHref="/menu"
        />
      </div>
    );
  }

  const hasAvailableVariant = product.variants.some((variant) => variant.is_available);

  return (
    <div className="page-container detail-page has-sticky-cta">
      <PageHeader
        title={product.category?.name ?? 'Menu'}
        description="Choose size and add to cart"
        showBack
        rightSlot={<FavouriteToggle productId={product.id} />}
      />

      {errorMessage ? <p className="form-feedback form-feedback-error">{errorMessage}</p> : null}

      <section className="detail-hero motion-enter">
        <div className="detail-image-wrap">
          <ProductImage
            name={product.name}
            imagePath={product.image_path}
            alt={product.name}
            className="detail-image"
            eager
          />
        </div>

        <div className="detail-panel">
          <div className="detail-heading">
            <h1 className="detail-title">{product.name}</h1>
            <p className="detail-price-live" aria-live="polite">
              {selectedVariant ? formatCurrency(selectedVariant.price) : 'Unavailable'}
            </p>
          </div>

          <ProductBadges product={product} showCustomizable />

          <p className="detail-description">
            {product.description || product.short_description || 'Freshly prepared for quick pickup.'}
          </p>

          {product.customer_ingredient_summary ? (
            <div className="detail-info-block">
              <span className="detail-info-label">About this drink</span>
              <p>{product.customer_ingredient_summary}</p>
            </div>
          ) : null}

          {(selectedVariant?.major_ingredients?.length ?? 0) > 0 ? (
            <div className="detail-info-block">
              <span className="detail-info-label">Contains</span>
              <div className="detail-ingredient-chips" aria-label="Major ingredients">
                {selectedVariant?.major_ingredients?.map((ingredient, index) => (
                  <span
                    key={ingredient.id}
                    className="detail-ingredient-chip motion-chip"
                    style={{ animationDelay: `${Math.min(index, 6) * 40}ms` }}
                  >
                    {ingredient.label}
                  </span>
                ))}
              </div>
            </div>
          ) : null}

          {product.flavours.length > 0 ? (
            <div className="detail-info-block">
              <span className="detail-info-label">Available flavours</span>
              <div className="detail-flavour-chips">
                {product.flavours.map((flavour) => (
                  <span key={flavour.id} className="detail-flavour-chip">
                    {flavour.name}
                  </span>
                ))}
              </div>
              {product.is_customizable ? (
                <p className="detail-meta">Customizable — tell the barista your preferred flavour at pickup.</p>
              ) : null}
            </div>
          ) : null}

          {product.preparation_time_minutes ? (
            <p className="detail-meta">{product.preparation_time_minutes} min prep</p>
          ) : null}

          <div className="variant-group">
            <div className="variant-group-header">
              <h2>Choose a size</h2>
              {selectedVariant ? (
                <span className="detail-selected-size" aria-live="polite">
                  {selectedVariant.name} · {formatCurrency(selectedVariant.price)}
                </span>
              ) : null}
            </div>
            <div className="variant-options" role="radiogroup" aria-label="Size options">
              {product.variants.map((variant) => {
                const isSelected = selectedVariant?.id === variant.id;
                const isDisabled = !variant.is_available;

                return (
                  <button
                    type="button"
                    key={variant.id}
                    role="radio"
                    aria-checked={isSelected}
                    disabled={isDisabled}
                    className={`variant-option ${isSelected ? 'active' : ''} ${isDisabled ? 'is-disabled' : ''}`}
                    onClick={() => setSelectedVariantId(variant.id)}
                  >
                    <span>{variant.name}</span>
                    <small>{isDisabled ? 'Unavailable' : variant.serving_size.label}</small>
                    <strong>{formatCurrency(variant.price)}</strong>
                  </button>
                );
              })}
            </div>
          </div>

          {!hasAvailableVariant ? (
            <p className="summary-warning">This drink is currently unavailable. Browse another size or menu item.</p>
          ) : null}
        </div>
      </section>

      <div className="page-note">
        <span>Looking for more like this?</span>
        <Link to={product.category ? `/menu?category=${product.category.id}` : '/menu'}>
          {product.category ? `More in ${product.category.name}` : 'Back to menu'}
        </Link>
      </div>

      <StickyActionBar
        eyebrow={cartQuantity > 0 ? 'In your cart' : 'Add to cart'}
        title={selectedVariant?.is_available ? selectedVariant.name : 'Unavailable'}
        value={formatCurrency(lineTotal)}
        note={
          !selectedVariant?.is_available
            ? 'Pick an available size'
            : cartQuantity > 0
              ? `${cartQuantity} × ${formatCurrency(selectedVariant.price)}`
              : formatCurrency(selectedVariant.price)
        }
      >
        {product && selectedVariant ? (
          <ProductCartControl
            product={product}
            variant={selectedVariant}
            size="lg"
            addLabel="Add"
            className="product-detail-cart-control"
          />
        ) : null}
      </StickyActionBar>
    </div>
  );
}
