import { useEffect, useId, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { ApiError } from '../../api/client';
import { deleteProductRating, submitProductRating, updateProductRating } from '../../api/ratings';
import { useProductOverlay } from '../../hooks/useProductOverlay';
import { useToastStore } from '../../stores/toastStore';
import { MyProductRating, RatingSummary } from '../../types/rating';
import { RatingStars } from './RatingStars';

interface RatingSheetProps {
  open: boolean;
  onClose: () => void;
  productId: number;
  productName: string;
  initialRating?: MyProductRating | null;
  onSaved: (payload: {
    my_rating: MyProductRating | null;
    rating_summary: RatingSummary;
    can_rate: boolean;
  }) => void;
}

export function RatingSheet({
  open,
  onClose,
  productId,
  productName,
  initialRating = null,
  onSaved,
}: RatingSheetProps) {
  const titleId = useId();
  const closeButtonRef = useRef<HTMLButtonElement>(null);
  const toastSuccess = useToastStore((state) => state.success);
  const toastError = useToastStore((state) => state.error);
  const [rating, setRating] = useState(initialRating?.rating ?? 0);
  const [review, setReview] = useState(initialRating?.review ?? '');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isDeleting, setIsDeleting] = useState(false);
  const editing = Boolean(initialRating);

  useEffect(() => {
    if (!open) {
      return;
    }

    setRating(initialRating?.rating ?? 0);
    setReview(initialRating?.review ?? '');
  }, [open, initialRating]);

  useProductOverlay({
    open,
    historyKey: `product-rating:${productId}`,
    onClose,
    focusRef: closeButtonRef,
  });

  if (!open || typeof document === 'undefined') {
    return null;
  }

  async function handleSubmit(): Promise<void> {
    if (rating < 1 || rating > 5 || isSubmitting) {
      return;
    }

    setIsSubmitting(true);

    try {
      const payload = {
        rating,
        review: review.trim() || null,
      };
      const response = editing
        ? await updateProductRating(productId, payload)
        : await submitProductRating(productId, payload);

      onSaved(response.data);
      toastSuccess('Thanks for your rating');
      onClose();
    } catch (error) {
      toastError(error instanceof ApiError ? error.message : 'Unable to save your rating.');
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleDelete(): Promise<void> {
    if (!editing || isDeleting) {
      return;
    }

    setIsDeleting(true);

    try {
      const response = await deleteProductRating(productId);
      onSaved(response.data);
      toastSuccess('Rating removed');
      onClose();
    } catch (error) {
      toastError(error instanceof ApiError ? error.message : 'Unable to remove your rating.');
    } finally {
      setIsDeleting(false);
    }
  }

  return createPortal(
    <div className="product-overlay product-overlay-rating is-open" role="presentation">
      <button type="button" className="product-overlay-backdrop" aria-label="Close rating form" onClick={onClose} />

      <div
        className="product-overlay-panel product-overlay-panel-rating"
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        onClick={(event) => event.stopPropagation()}
      >
        <div className="product-overlay-handle" aria-hidden="true" />

        <div className="product-overlay-header">
          <button
            ref={closeButtonRef}
            type="button"
            className="product-overlay-close"
            aria-label="Close rating form"
            onClick={onClose}
          >
            <i className="bi bi-x-lg" aria-hidden="true" />
          </button>
        </div>

        <div className="product-overlay-body rating-sheet-body">
          <span className="auth-badge">Verified purchase</span>
          <h2 id={titleId} className="product-overlay-title">
            How was your drink?
          </h2>
          <p className="product-overlay-description">{productName}</p>

          <RatingStars value={rating} interactive size="lg" onChange={setRating} />

          <label className="rating-sheet-label" htmlFor={`rating-review-${productId}`}>
            Tell us what you liked
            <span>Optional</span>
          </label>
          <textarea
            id={`rating-review-${productId}`}
            className="rating-sheet-textarea"
            rows={3}
            maxLength={1000}
            value={review}
            onChange={(event) => setReview(event.target.value)}
            placeholder="Short note about flavour, temperature, or service"
          />

          <button
            type="button"
            className="primary-button"
            disabled={rating < 1 || isSubmitting}
            onClick={() => void handleSubmit()}
          >
            {isSubmitting ? 'Saving…' : editing ? 'Update rating' : 'Submit rating'}
          </button>

          {editing ? (
            <button
              type="button"
              className="link-button rating-sheet-delete"
              disabled={isDeleting}
              onClick={() => void handleDelete()}
            >
              {isDeleting ? 'Removing…' : 'Delete rating'}
            </button>
          ) : null}
        </div>
      </div>
    </div>,
    document.body,
  );
}
