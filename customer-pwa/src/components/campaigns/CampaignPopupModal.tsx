import { useEffect, useId, useLayoutEffect, useRef } from 'react';
import { createPortal } from 'react-dom';
import { EligibleCampaign } from '../../api/campaigns';
import { lockOverlayBackgroundScroll, unlockOverlayBackgroundScroll } from '../../utils/overlayScrollLock';

interface CampaignPopupModalProps {
  campaign: EligibleCampaign;
  open: boolean;
  onClose: () => void;
  onCta: () => void;
}

export function CampaignPopupModal({ campaign, open, onClose, onCta }: CampaignPopupModalProps) {
  const titleId = useId();
  const descriptionId = useId();
  const closeRef = useRef<HTMLButtonElement>(null);
  const ctaLockRef = useRef(false);
  const previouslyFocusedRef = useRef<HTMLElement | null>(null);

  useLayoutEffect(() => {
    if (!open) {
      ctaLockRef.current = false;

      return;
    }

    previouslyFocusedRef.current =
      document.activeElement instanceof HTMLElement ? document.activeElement : null;

    lockOverlayBackgroundScroll();
    closeRef.current?.focus({ preventScroll: true });

    return () => {
      unlockOverlayBackgroundScroll();

      const trigger = previouslyFocusedRef.current;
      previouslyFocusedRef.current = null;

      if (trigger && document.contains(trigger)) {
        trigger.focus({ preventScroll: true });
      }
    };
  }, [open]);

  useEffect(() => {
    if (!open) {
      return;
    }

    const onKeyDown = (event: KeyboardEvent): void => {
      if (event.key === 'Escape') {
        onClose();
      }
    };

    window.addEventListener('keydown', onKeyDown);

    return () => window.removeEventListener('keydown', onKeyDown);
  }, [open, onClose]);

  function handleCta(): void {
    if (ctaLockRef.current) {
      return;
    }

    ctaLockRef.current = true;
    onCta();
  }

  if (!open || typeof document === 'undefined') {
    return null;
  }

  const showPrimary = campaign.cta.type !== 'close' && Boolean(campaign.cta_label);
  const dismissLabel = campaign.cta.type === 'close' && campaign.cta_label ? campaign.cta_label : 'Not now';
  const describedBy = campaign.message ? descriptionId : undefined;

  return createPortal(
    <div
      className="confirm-dialog-overlay campaign-popup-overlay"
      role="presentation"
      onClick={onClose}
    >
      <div
        className="confirm-dialog-panel campaign-popup-panel"
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        aria-describedby={describedBy}
        onClick={(event) => event.stopPropagation()}
      >
        <header className="confirm-dialog-header">
          <h2 id={titleId}>{campaign.title}</h2>
          <button
            ref={closeRef}
            type="button"
            className="product-overlay-close"
            aria-label="Close"
            onClick={onClose}
          >
            <i className="bi bi-x-lg" aria-hidden="true"></i>
          </button>
        </header>
        <div className="confirm-dialog-body campaign-popup-body">
          {campaign.image_url ? (
            <img
              src={campaign.image_url}
              alt=""
              className="campaign-popup-image"
              onError={(event) => {
                event.currentTarget.style.display = 'none';
              }}
            />
          ) : null}
          {campaign.message ? (
            <p id={descriptionId} className="campaign-popup-message">
              {campaign.message}
            </p>
          ) : null}
        </div>
        <div className="campaign-popup-actions">
          {showPrimary ? (
            <button type="button" className="btn btn-primary rounded-pill w-100" onClick={handleCta}>
              {campaign.cta_label}
            </button>
          ) : null}
          <button type="button" className="link-button campaign-popup-dismiss" onClick={onClose}>
            {dismissLabel}
          </button>
        </div>
      </div>
    </div>,
    document.body,
  );
}
