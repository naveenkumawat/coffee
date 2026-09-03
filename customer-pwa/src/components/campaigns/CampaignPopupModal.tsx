import { useEffect, useId, useLayoutEffect, useRef } from 'react';
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
  const closeRef = useRef<HTMLButtonElement>(null);

  useLayoutEffect(() => {
    if (!open) {
      return;
    }

    lockOverlayBackgroundScroll();

    return () => {
      unlockOverlayBackgroundScroll();
    };
  }, [open]);

  useEffect(() => {
    if (!open) {
      return;
    }

    closeRef.current?.focus();

    const onKeyDown = (event: KeyboardEvent): void => {
      if (event.key === 'Escape') {
        onClose();
      }
    };

    window.addEventListener('keydown', onKeyDown);

    return () => window.removeEventListener('keydown', onKeyDown);
  }, [open, onClose]);

  if (!open) {
    return null;
  }

  return (
    <div className="product-overlay campaign-popup-overlay" role="presentation" onClick={onClose}>
      <div
        className="product-overlay-panel campaign-popup-panel"
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        onClick={(event) => event.stopPropagation()}
      >
        <div className="product-overlay-handle" aria-hidden="true" />
        <header className="product-overlay-header">
          <h2 id={titleId}>{campaign.title}</h2>
          <button ref={closeRef} type="button" className="icon-button" aria-label="Close campaign" onClick={onClose}>
            ×
          </button>
        </header>
        <div className="product-overlay-body campaign-popup-body">
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
          {campaign.message ? <p className="campaign-popup-message">{campaign.message}</p> : null}
          <div className="campaign-popup-actions">
            {campaign.cta.type !== 'close' && campaign.cta_label ? (
              <button type="button" className="btn btn-primary rounded-pill w-100" onClick={onCta}>
                {campaign.cta_label}
              </button>
            ) : null}
            <button type="button" className="btn btn-light rounded-pill w-100" onClick={onClose}>
              {campaign.cta.type === 'close' && campaign.cta_label ? campaign.cta_label : 'Not now'}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
