import { useEffect, useId, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { fetchProduct } from '../../api/catalog';
import { ApiError } from '../../api/client';
import { useProductOverlay } from '../../hooks/useProductOverlay';
import { Product } from '../../types/catalog';
import { formatCurrency } from '../../utils/format';
import { getPreferredVariant, getProductVariants } from '../../utils/productActions';
import { ProductImage } from '../common/ProductImage';
import { FavouriteToggle } from './FavouriteToggle';
import { ProductBadges } from './ProductBadges';
import { ProductCartControl } from './ProductCartControl';

interface ProductDetailSheetProps {
  product: Product;
  open: boolean;
  onClose: () => void;
  showFavouriteToggle?: boolean;
}

export function ProductDetailSheet({
  product: initialProduct,
  open,
  onClose,
  showFavouriteToggle = true,
}: ProductDetailSheetProps) {
  const titleId = useId();
  const descriptionId = useId();
  const closeButtonRef = useRef<HTMLButtonElement>(null);
  const [product, setProduct] = useState(initialProduct);
  const [selectedVariantId, setSelectedVariantId] = useState<number | null>(
    () => getPreferredVariant(initialProduct)?.id ?? null,
  );
  const [loadError, setLoadError] = useState<string | null>(null);

  useEffect(() => {
    if (!open) {
      return;
    }

    setProduct(initialProduct);
    setSelectedVariantId(getPreferredVariant(initialProduct)?.id ?? null);
    setLoadError(null);

    let cancelled = false;

    void fetchProduct(String(initialProduct.id))
      .then((response) => {
        if (cancelled) {
          return;
        }

        setProduct(response.data);
        setSelectedVariantId((current) => {
          const nextPreferred = getPreferredVariant(response.data);
          const stillValid = response.data.variants.some(
            (variant) => variant.id === current && variant.is_available,
          );

          return stillValid ? current : nextPreferred?.id ?? null;
        });
      })
      .catch((error) => {
        if (!cancelled) {
          setLoadError(error instanceof ApiError ? error.message : null);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [open, initialProduct]);

  useProductOverlay({
    open,
    historyKey: `product-detail:${initialProduct.id}`,
    onClose,
    focusRef: closeButtonRef,
  });

  const variants = getProductVariants(product);
  const selectedVariant = useMemo(
    () => variants.find((variant) => variant.id === selectedVariantId) ?? null,
    [variants, selectedVariantId],
  );
  const majorIngredients = selectedVariant?.major_ingredients ?? [];

  if (!open || typeof document === 'undefined') {
    return null;
  }

  return createPortal(
    <div className="product-overlay product-overlay-detail is-open" role="presentation">
      <button
        type="button"
        className="product-overlay-backdrop"
        aria-label="Close product details"
        onClick={onClose}
      />

      <div
        className="product-overlay-panel product-overlay-panel-detail"
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        aria-describedby={descriptionId}
      >
        <div className="product-overlay-handle" aria-hidden="true" />

        <div className="product-overlay-header">
          <button
            ref={closeButtonRef}
            type="button"
            className="product-overlay-close"
            aria-label="Close"
            onClick={onClose}
          >
            <i className="bi bi-x-lg" aria-hidden="true"></i>
          </button>
          {showFavouriteToggle ? <FavouriteToggle productId={product.id} /> : null}
        </div>

        <div className="product-overlay-scroll">
          <div className="product-detail-sheet-media">
            <ProductImage
              name={product.name}
              imagePath={product.image_path}
              alt={product.name}
              className="product-detail-sheet-image"
              eager
            />
            <ProductBadges product={product} showCustomizable />
          </div>

          <div className="product-overlay-body">
            <div className="product-overlay-heading">
              <h2 id={titleId} className="product-overlay-title">
                {product.name}
              </h2>
              <p className="product-overlay-price" aria-live="polite">
                {selectedVariant ? formatCurrency(selectedVariant.price) : 'Unavailable'}
              </p>
            </div>

            <p id={descriptionId} className="product-overlay-description">
              {product.short_description || product.description || 'Freshly prepared for quick pickup.'}
            </p>

            {loadError ? (
              <p className="product-overlay-note" role="status">
                Showing saved menu details. Live availability may be delayed.
              </p>
            ) : null}

            {product.flavours.length > 0 ? (
              <div className="product-overlay-block">
                <span className="product-overlay-label">Flavours</span>
                <div className="detail-flavour-chips">
                  {product.flavours.map((flavour) => (
                    <span key={flavour.id} className="detail-flavour-chip">
                      {flavour.name}
                    </span>
                  ))}
                </div>
                {product.is_customizable ? (
                  <p className="product-overlay-note">
                    Customizable — tell the barista your preferred flavour at pickup.
                  </p>
                ) : null}
              </div>
            ) : product.is_customizable ? (
              <p className="product-overlay-note">
                Customizable — tell the barista your preferred flavour at pickup.
              </p>
            ) : null}

            {product.customer_ingredient_summary ? (
              <div className="product-overlay-block">
                <span className="product-overlay-label">About this drink</span>
                <p>{product.customer_ingredient_summary}</p>
              </div>
            ) : null}

            {majorIngredients.length > 0 ? (
              <div className="product-overlay-block">
                <span className="product-overlay-label">Contains</span>
                <div className="detail-ingredient-chips" aria-label="Major ingredients">
                  {majorIngredients.map((ingredient) => (
                    <span key={ingredient.id} className="detail-ingredient-chip">
                      {ingredient.label}
                    </span>
                  ))}
                </div>
              </div>
            ) : null}

            {variants.length > 0 ? (
              <div className="product-overlay-block">
                <div className="product-overlay-block-header">
                  <span className="product-overlay-label">
                    {variants.length > 1 ? 'Sizes' : 'Size'}
                  </span>
                  {selectedVariant ? (
                    <span className="product-overlay-selected" aria-live="polite">
                      {selectedVariant.name} · {formatCurrency(selectedVariant.price)}
                    </span>
                  ) : null}
                </div>
                <div className="quick-add-variants is-detail" role="radiogroup" aria-label="Size options">
                  {variants.map((variant) => {
                    const isSelected = selectedVariant?.id === variant.id;
                    const isDisabled = !variant.is_available;

                    return (
                      <button
                        type="button"
                        key={variant.id}
                        role="radio"
                        aria-checked={isSelected}
                        disabled={isDisabled}
                        className={`quick-add-variant ${isSelected ? 'is-selected' : ''} ${isDisabled ? 'is-disabled' : ''}`}
                        onClick={() => setSelectedVariantId(variant.id)}
                      >
                        <span className="quick-add-variant-name">{variant.name}</span>
                        <small className="quick-add-variant-meta">
                          {isDisabled ? 'Unavailable' : variant.serving_size.label}
                        </small>
                        <strong className="quick-add-variant-price">{formatCurrency(variant.price)}</strong>
                      </button>
                    );
                  })}
                </div>
              </div>
            ) : null}
          </div>
        </div>

        <div className="product-overlay-footer">
          <div className="product-overlay-footer-meta">
            <span>{selectedVariant?.is_available ? selectedVariant.name : 'Unavailable'}</span>
            <strong>
              {selectedVariant?.is_available ? formatCurrency(selectedVariant.price) : '—'}
            </strong>
          </div>
          {selectedVariant ? (
            <ProductCartControl
              product={product}
              variant={selectedVariant}
              size="lg"
              addLabel="Add to order"
              className="product-overlay-cart"
            />
          ) : (
            <span className="product-card-action is-disabled">Unavailable</span>
          )}
        </div>
      </div>
    </div>,
    document.body,
  );
}
