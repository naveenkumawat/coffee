import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { fetchProduct } from '../api/catalog';
import { ApiError } from '../api/client';
import { fetchProductRatings } from '../api/ratings';
import { FavouriteToggle } from '../components/catalog/FavouriteToggle';
import { ProductOrderControl } from '../components/catalog/ProductOrderControl';
import { ProductReviewsBlock } from '../components/catalog/ProductReviewsBlock';
import { ProductTags } from '../components/catalog/ProductTags';
import { EmptyState } from '../components/common/EmptyState';
import { ErrorState } from '../components/common/ErrorState';
import { LoadingSkeleton } from '../components/common/LoadingSkeleton';
import { PageHeader } from '../components/common/PageHeader';
import { ProductImage } from '../components/common/ProductImage';
import { RecommendationSection } from '../components/recommendations/RecommendationSection';
import { Product } from '../types/catalog';
import { PublicProductReview, RatingSummary } from '../types/rating';
import { getProductVariants, isProductUnavailable } from '../utils/productActions';
import { trackBehaviour } from '../tracking/behaviourTracker';

export function ProductDetailPage() {
  const { productId = '' } = useParams();
  const [product, setProduct] = useState<Product | null>(null);
  const [ratingSummary, setRatingSummary] = useState<RatingSummary | null>(null);
  const [reviews, setReviews] = useState<PublicProductReview[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  useEffect(() => {
    async function load(): Promise<void> {
      setIsLoading(true);
      setErrorMessage(null);

      try {
        const [productResponse, ratingsResponse] = await Promise.all([
          fetchProduct(productId),
          fetchProductRatings(productId, 1, 20),
        ]);
        setProduct(productResponse.data);
        setRatingSummary(ratingsResponse.data.rating_summary);
        setReviews(ratingsResponse.data.reviews);
        trackBehaviour({
          event_type: 'product_viewed',
          product_id: productResponse.data.id,
          product_category_id: productResponse.data.category?.id ?? undefined,
          metadata: { source: 'product_detail' },
          dedupe_key: `product_viewed:${productResponse.data.id}`,
        });
      } catch (error) {
        setErrorMessage(error instanceof ApiError ? error.message : 'Unable to load this drink.');
      } finally {
        setIsLoading(false);
      }
    }

    void load();
  }, [productId]);

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

  const unavailable = isProductUnavailable(product);
  const variants = getProductVariants(product);

  return (
    <div className="page-container detail-page">
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
            fit="cover"
            eager
          />
        </div>

        <div className="detail-panel">
          <div className="detail-heading">
            <h1 className="detail-title">{product.name}</h1>
          </div>

          <ProductTags
            tags={product.tags}
            mode="detail"
            showCustomizable
            isCustomizable={product.is_customizable}
          />

          <p className="detail-description">
            {product.description || product.short_description || 'Freshly prepared for quick pickup.'}
          </p>

          <ProductReviewsBlock
            summary={ratingSummary}
            reviews={reviews}
            productId={product.id}
            previewLimit={20}
            showViewAll={false}
          />

          {product.customer_ingredient_summary ? (
            <div className="detail-info-block">
              <span className="detail-info-label">About this drink</span>
              <p>{product.customer_ingredient_summary}</p>
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

          <div className="detail-order-block">
            <h2 className="detail-order-heading">{variants.length > 1 ? 'Choose sizes' : 'Add to order'}</h2>
            <ProductOrderControl product={product} mode="full" className="detail-order-control" />
            {unavailable ? (
              <p className="summary-warning">This drink is currently unavailable. Browse another menu item.</p>
            ) : null}
          </div>
        </div>
      </section>

      <div className="page-note">
        <span>Looking for more like this?</span>
        <Link to={product.category ? `/menu?category=${product.category.id}` : '/menu'}>
          {product.category ? `More in ${product.category.name}` : 'Back to menu'}
        </Link>
      </div>

      <RecommendationSection
        context="product_detail"
        placement="product_detail_rail"
        productId={product.id}
        categoryId={product.category?.id}
        excludeProductIds={[product.id]}
        limit={8}
        title="You may also like"
      />
    </div>
  );
}
