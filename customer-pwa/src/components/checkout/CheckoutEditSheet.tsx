import { ReactNode, useId, useRef } from 'react';
import { createPortal } from 'react-dom';
import { useProductOverlay } from '../../hooks/useProductOverlay';

interface CheckoutEditSheetProps {
  open: boolean;
  title: string;
  historyKey: string;
  onClose: () => void;
  onDone: () => void;
  children: ReactNode;
  doneLabel?: string;
}

export function CheckoutEditSheet({
  open,
  title,
  historyKey,
  onClose,
  onDone,
  children,
  doneLabel = 'Done',
}: CheckoutEditSheetProps) {
  const titleId = useId();
  const closeButtonRef = useRef<HTMLButtonElement>(null);

  useProductOverlay({
    open,
    historyKey,
    onClose,
    focusRef: closeButtonRef,
  });

  if (!open || typeof document === 'undefined') {
    return null;
  }

  return createPortal(
    <div className="product-overlay is-open" role="presentation">
      <button
        type="button"
        className="product-overlay-backdrop"
        aria-label="Close"
        onClick={onClose}
      />
      <div
        className="product-overlay-panel product-overlay-panel-compact"
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
      >
        <div className="product-overlay-handle" aria-hidden="true" />
        <header className="product-overlay-header product-overlay-header-compact">
          <h2 id={titleId} className="product-overlay-title">
            {title}
          </h2>
          <button
            ref={closeButtonRef}
            type="button"
            className="product-overlay-close"
            aria-label="Close"
            onClick={onClose}
          >
            <i className="bi bi-x-lg" aria-hidden="true"></i>
          </button>
        </header>
        <div className="product-overlay-scroll">
          <div className="product-overlay-body checkout-edit-sheet-body">{children}</div>
        </div>
        <footer className="product-overlay-footer">
          <button type="button" className="btn btn-primary btn-lg rounded-pill w-100" onClick={onDone}>
            {doneLabel}
          </button>
        </footer>
      </div>
    </div>,
    document.body,
  );
}
