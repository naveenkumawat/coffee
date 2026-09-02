import { useEffect, useId, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { fetchProduct } from '../../api/catalog';
import { ApiError } from '../../api/client';
import { fetchProductRatings } from '../../api/ratings';
import { useProductOverlay } from '../../hooks/useProductOverlay';
import { Product } from '../../types/catalog';
import { PublicProductReview, RatingSummary } from '../../types/rating';
import { ProductImage } from '../common/ProductImage';
import { FavouriteToggle } from './FavouriteToggle';
import { ProductOrderControl, ProductOrderHandler } from './ProductOrderControl';
import { ProductReviewsBlock } from './ProductReviewsBlock';
import { ProductTags } from './ProductTags';

interface ProductDetailSheetProps {
  product: Product;
  open: boolean;
  onClose: () => void;
  showFavouriteToggle?: boolean;
  orderHandler?: ProductOrderHandler;
  sheetCtaLabel?: string;
}

export function ProductDetailSheet({
  product: initialProduct,
  open,
  onClose,
  showFavouriteToggle = true,
  orderHandler,
  sheetCtaLabel,
}: ProductDetailSheetProps) {
  const titleId = useId();
  const descriptionId = useId();
  const closeButtonRef = useRef<HTMLButtonElement>(null);
  const [product, setProduct] = useState(initialProduct);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [ratingSummary, setRatingSummary] = useState<RatingSummary | null>(initialProduct.rating_summary ?? null);
  const [reviews, setReviews] = useState<PublicProductReview[]>([]);

  useEffect(() => {
    if (!open) {
      return;
    }

    setProduct(initialProduct);
    setRatingSummary(initialProduct.rating_summary ?? null);
    setLoadError(null);
    setReviews([]);

    let cancelled = false;

    void Promise.all([
      fetchProduct(String(initialProduct.id)),
      fetchProductRatings(initialProduct.id, 1, 3),
    ])
      .then(([productResponse, ratingsResponse]) => {
        if (cancelled) {
          return;
        }

        setProduct(productResponse.data);
        setRatingSummary(ratingsResponse.data.rating_summary);
        setReviews(ratingsResponse.data.reviews);
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

            <ProductReviewsBlock
              summary={ratingSummary}
              reviews={reviews}
              productId={product.id}
              previewLimit={2}
              compact
            />

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
          <ProductOrderControl
            product={product}
            mode="full"
            className="product-overlay-order-control"
            orderHandler={orderHandler}
            sheetCtaLabel={sheetCtaLabel}
          />
        </div>
      </div>
    </div>,
    document.body,
  );
}
