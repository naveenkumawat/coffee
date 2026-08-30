import { useEffect, useId, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { useProductOverlay } from '../../hooks/useProductOverlay';
import { useCartStore } from '../../stores/cartStore';
import { Product } from '../../types/catalog';
import { quantityForVariant } from '../../utils/cartQuantity';
import { formatCurrency } from '../../utils/format';
import { getPreferredVariant, getProductVariants } from '../../utils/productActions';
import { ProductImage } from '../common/ProductImage';
import { ProductCartControl } from './ProductCartControl';

interface QuickAddSheetProps {
  product: Product;
  open: boolean;
  onClose: () => void;
}

/** Fallback sheet for unusual/non-standard size structures. */
export function QuickAddSheet({ product, open, onClose }: QuickAddSheetProps) {
  const titleId = useId();
  const closeButtonRef = useRef<HTMLButtonElement>(null);
  const cart = useCartStore((state) => state.cart);
  const [selectedVariantId, setSelectedVariantId] = useState<number | null>(
    () => getPreferredVariant(product)?.id ?? null,
  );

  useEffect(() => {
    if (!open) {
      return;
    }

    setSelectedVariantId(getPreferredVariant(product)?.id ?? null);
  }, [open, product]);

  useProductOverlay({
    open,
    historyKey: `quick-add:${product.id}`,
    onClose,
    focusRef: closeButtonRef,
  });

  const variants = getProductVariants(product);
  const selectedVariant = useMemo(
    () => variants.find((variant) => variant.id === selectedVariantId) ?? null,
    [variants, selectedVariantId],
  );
  const existingQuantity = selectedVariant ? quantityForVariant(cart, selectedVariant.id) : 0;

  if (!open || typeof document === 'undefined') {
    return null;
  }

  return createPortal(
    <div className="product-overlay product-overlay-quick-add is-open" role="presentation">
      <button
        type="button"
        className="product-overlay-backdrop"
        aria-label="Close size selection"
        onClick={onClose}
      />

      <div
        className="product-overlay-panel product-overlay-panel-compact"
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
      >
        <div className="product-overlay-handle" aria-hidden="true" />

        <div className="product-overlay-header product-overlay-header-compact">
          <div className="quick-add-identity">
            <div className="quick-add-thumb">
              <ProductImage name={product.name} imagePath={product.image_path} alt="" eager />
            </div>
            <div className="quick-add-copy">
              <h2 id={titleId} className="product-overlay-title">
                {product.name}
              </h2>
              <p className="product-overlay-price" aria-live="polite">
                {selectedVariant ? formatCurrency(selectedVariant.price) : 'Unavailable'}
              </p>
            </div>
          </div>
          <button
            ref={closeButtonRef}
            type="button"
            className="product-overlay-close"
            aria-label="Close"
            onClick={onClose}
          >
            <i className="bi bi-x-lg" aria-hidden="true"></i>
          </button>
        </div>

        <div className="product-overlay-body product-overlay-body-compact">
          <div className="quick-add-variants" role="radiogroup" aria-label="Size options">
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

        <div className="product-overlay-footer product-overlay-footer-compact">
          <div className="product-overlay-footer-meta">
            <span>{selectedVariant?.is_available ? selectedVariant.name : 'Pick a size'}</span>
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
              onAdded={existingQuantity <= 0 ? onClose : undefined}
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
