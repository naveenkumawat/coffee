import { ReactNode } from 'react';

interface CompactCartCheckoutBarProps {
  totalLabel: string;
  ctaLabel: string;
  disabled?: boolean;
  note?: string | null;
  onCheckout: () => void;
}

/** Compact single-row checkout CTA for the cart page. */
export function CompactCartCheckoutBar({
  totalLabel,
  ctaLabel,
  disabled = false,
  note = null,
  onCheckout,
}: CompactCartCheckoutBarProps) {
  return (
    <>
      <div className="sticky-action-spacer sticky-action-spacer--compact" aria-hidden="true" />
      <section className="sticky-action-bar sticky-action-bar--compact" aria-label="Checkout">
        <div className="compact-action-row">
          <div className="compact-action-total">
            <span>Total</span>
            <strong>{totalLabel}</strong>
          </div>
          <button
            type="button"
            className="btn btn-primary rounded-pill compact-action-cta"
            disabled={disabled}
            onClick={onCheckout}
          >
            {ctaLabel}
          </button>
        </div>
        {note ? <p className="compact-action-note">{note}</p> : null}
      </section>
    </>
  );
}

interface CheckoutSubmitBarProps {
  totalLabel: string;
  ctaLabel: string;
  disabled?: boolean;
  busy?: boolean;
  note?: string | null;
  children?: ReactNode;
}

/** Compact place-order bar for checkout. */
export function CheckoutSubmitBar({
  totalLabel,
  ctaLabel,
  disabled = false,
  busy = false,
  note = null,
  children,
}: CheckoutSubmitBarProps) {
  return (
    <>
      <div className="sticky-action-spacer sticky-action-spacer--compact" aria-hidden="true" />
      <section className="sticky-action-bar sticky-action-bar--compact" aria-label="Place order">
        <div className="compact-action-row">
          <div className="compact-action-total">
            <span>Total</span>
            <strong>{totalLabel}</strong>
          </div>
          {children ?? (
            <button
              type="submit"
              className="btn btn-primary rounded-pill compact-action-cta"
              disabled={disabled}
              aria-busy={busy}
            >
              {ctaLabel}
            </button>
          )}
        </div>
        {note ? <p className="compact-action-note">{note}</p> : null}
      </section>
    </>
  );
}
