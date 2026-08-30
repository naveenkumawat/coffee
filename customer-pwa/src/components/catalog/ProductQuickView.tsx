import { useEffect, useId, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { fetchProduct } from '../../api/catalog';
import { ApiError } from '../../api/client';
import { Product } from '../../types/catalog';
import { formatCurrency } from '../../utils/format';
import { getPreferredVariant, getProductVariants } from '../../utils/productActions';
import { ProductImage } from '../common/ProductImage';
import { FavouriteToggle } from './FavouriteToggle';
import { ProductBadges } from './ProductBadges';
import { ProductCartControl } from './ProductCartControl';

interface ProductQuickViewProps {
  product: Product;
  open: boolean;
  onClose: () => void;
  showFavouriteToggle?: boolean;
}

export function ProductQuickView({
  product: initialProduct,
  open,
  onClose,
  showFavouriteToggle = true,
}: ProductQuickViewProps) {
  const titleId = useId();
  const descriptionId = useId();
  const closeButtonRef = useRef<HTMLButtonElement>(null);
  const previouslyFocusedRef = useRef<HTMLElement | null>(null);
  const pushedHistoryRef = useRef(false);
  const closedByPopRef = useRef(false);
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
        if (cancelled) {
          return;
        }

        setLoadError(error instanceof ApiError ? error.message : null);
      });

    return () => {
      cancelled = true;
    };
  }, [open, initialProduct]);

  useEffect(() => {
    if (!open) {
      return;
    }

    previouslyFocusedRef.current = document.activeElement instanceof HTMLElement
      ? document.activeElement
      : null;

    const frame = window.requestAnimationFrame(() => {
      closeButtonRef.current?.focus();
    });

    const scrollY = window.scrollY;
    const { style } = document.body;
    const previousOverflow = style.overflow;
    const previousPosition = style.position;
    const previousTop = style.top;
    const previousWidth = style.width;

    style.overflow = 'hidden';
    style.position = 'fixed';
    style.top = `-${scrollY}px`;
    style.width = '100%';

    closedByPopRef.current = false;
    const historyKey = initialProduct.id;
    const alreadyOurs = window.history.state?.productQuickView === historyKey;

    if (!alreadyOurs) {
      pushedHistoryRef.current = true;
      window.history.pushState({ productQuickView: historyKey }, '');
    }

    const handlePopState = (): void => {
      closedByPopRef.current = true;
      pushedHistoryRef.current = false;
      onClose();
    };

    const handleKeyDown = (event: KeyboardEvent): void => {
      if (event.key === 'Escape') {
        event.preventDefault();
        onClose();
      }
    };

    window.addEventListener('popstate', handlePopState);
    window.addEventListener('keydown', handleKeyDown);

    return () => {
      window.cancelAnimationFrame(frame);
      window.removeEventListener('popstate', handlePopState);
      window.removeEventListener('keydown', handleKeyDown);

      style.overflow = previousOverflow;
      style.position = previousPosition;
      style.top = previousTop;
      style.width = previousWidth;
      window.scrollTo(0, scrollY);

      if (pushedHistoryRef.current && !closedByPopRef.current) {
        pushedHistoryRef.current = false;
        window.history.back();
      }

      previouslyFocusedRef.current?.focus?.();
    };
  }, [open, initialProduct.id, onClose]);

  const selectedVariant = useMemo(
    () => product.variants.find((variant) => variant.id === selectedVariantId) ?? null,
    [product, selectedVariantId],
  );

  const variants = getProductVariants(product);
  const majorIngredients = selectedVariant?.major_ingredients ?? [];

  if (!open || typeof document === 'undefined') {
    return null;
  }

  return createPortal(
    <div className="product-quick-view is-open" role="presentation">
      <button
        type="button"
        className="product-quick-view-backdrop"
        aria-label="Close product details"
        onClick={onClose}
      />

      <div
        className="product-quick-view-panel"
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        aria-describedby={descriptionId}
      >
        <div className="product-quick-view-handle" aria-hidden="true" />

        <div className="product-quick-view-toolbar">
          <button
            ref={closeButtonRef}
            type="button"
            className="product-quick-view-close"
            aria-label="Close"
            onClick={onClose}
          >
            <i className="bi bi-x-lg" aria-hidden="true"></i>
          </button>
          {showFavouriteToggle ? <FavouriteToggle productId={product.id} /> : null}
        </div>

        <div className="product-quick-view-scroll">
          <div className="product-quick-view-media">
            <ProductImage
              name={product.name}
              imagePath={product.image_path}
              alt={product.name}
              className="product-quick-view-image"
              eager
            />
            <ProductBadges product={product} showCustomizable />
          </div>

          <div className="product-quick-view-body">
            <div className="product-quick-view-heading">
              <h2 id={titleId} className="product-quick-view-title">
                {product.name}
              </h2>
              <p className="product-quick-view-price" aria-live="polite">
                {selectedVariant ? formatCurrency(selectedVariant.price) : 'Unavailable'}
              </p>
            </div>

            <p id={descriptionId} className="product-quick-view-description">
              {product.short_description || product.description || 'Freshly prepared for quick pickup.'}
            </p>

            {loadError ? (
              <p className="product-quick-view-note" role="status">
                Showing saved menu details. Live availability may be delayed.
              </p>
            ) : null}

            {product.flavours.length > 0 ? (
              <div className="product-quick-view-block">
                <span className="product-quick-view-label">Flavours</span>
                <div className="detail-flavour-chips">
                  {product.flavours.map((flavour) => (
                    <span key={flavour.id} className="detail-flavour-chip">
                      {flavour.name}
                    </span>
                  ))}
                </div>
                {product.is_customizable ? (
                  <p className="product-quick-view-note">
                    Customizable — tell the barista your preferred flavour at pickup.
                  </p>
                ) : null}
              </div>
            ) : null}

            {product.customer_ingredient_summary ? (
              <div className="product-quick-view-block">
                <span className="product-quick-view-label">About this drink</span>
                <p>{product.customer_ingredient_summary}</p>
              </div>
            ) : null}

            {majorIngredients.length > 0 ? (
              <div className="product-quick-view-block">
                <span className="product-quick-view-label">Contains</span>
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
              <div className="variant-group product-quick-view-variants">
                <div className="variant-group-header">
                  <h3>{variants.length > 1 ? 'Choose a size' : 'Size'}</h3>
                  {selectedVariant ? (
                    <span className="detail-selected-size" aria-live="polite">
                      {selectedVariant.name} · {formatCurrency(selectedVariant.price)}
                    </span>
                  ) : null}
                </div>
                <div className="variant-options" role="radiogroup" aria-label="Size options">
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
            ) : null}
          </div>
        </div>

        <div className="product-quick-view-footer">
          <div className="product-quick-view-footer-copy">
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
              addLabel="Add"
              className="product-quick-view-cart"
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
