import { useEffect, useId, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { fetchProduct } from '../../api/catalog';
import { ApiError } from '../../api/client';
import { useProductOverlay } from '../../hooks/useProductOverlay';
import { Product } from '../../types/catalog';
import { ProductImage } from '../common/ProductImage';
import { FavouriteToggle } from './FavouriteToggle';
import { ProductOrderControl } from './ProductOrderControl';
import { ProductTags } from './ProductTags';

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
  const [loadError, setLoadError] = useState<string | null>(null);

  useEffect(() => {
    if (!open) {
      return;
    }

    setProduct(initialProduct);
    setLoadError(null);

    let cancelled = false;

    void fetchProduct(String(initialProduct.id))
      .then((response) => {
        if (cancelled) {
          return;
        }

        setProduct(response.data);
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
        onClick={(event) => event.stopPropagation()}
      >
        <div className="product-overlay-handle" aria-hidden="true" />

        <div className="product-overlay-header">
          <button
            ref={closeButtonRef}
            type="button"
            className="product-overlay-close"
            aria-label="Close product details"
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
              fit="cover"
              eager
            />
            <ProductTags
              tags={product.tags}
              mode="detail"
              showCustomizable
              isCustomizable={product.is_customizable}
            />
          </div>

          <div className="product-overlay-body">
            <div className="product-overlay-heading">
              <h2 id={titleId} className="product-overlay-title">
                {product.name}
              </h2>
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

            {(product.default_variant?.major_ingredients?.length ?? 0) > 0 ||
            product.variants.some((variant) => (variant.major_ingredients?.length ?? 0) > 0) ? (
              <div className="product-overlay-block">
                <span className="product-overlay-label">Contains</span>
                <div className="detail-ingredient-chips" aria-label="Major ingredients">
                  {(
                    product.variants.find((variant) => (variant.major_ingredients?.length ?? 0) > 0)
                      ?.major_ingredients ??
                    product.default_variant?.major_ingredients ??
                    []
                  ).map((ingredient) => (
                    <span key={ingredient.id} className="detail-ingredient-chip">
                      {ingredient.label}
                    </span>
                  ))}
                </div>
              </div>
            ) : null}
          </div>
        </div>

        <div className="product-overlay-footer-order">
          <span className="product-overlay-label">Add to order</span>
          <ProductOrderControl product={product} mode="full" className="product-overlay-order-control" />
        </div>
      </div>
    </div>,
    document.body,
  );
}
