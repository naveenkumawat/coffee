import { ReactNode } from 'react';

interface StickyActionBarProps {
  eyebrow?: string;
  title: string;
  value: string;
  note?: string;
  children: ReactNode;
}

/** Stacked sticky action panel (waiter flows). Prefer Compact* bars for customer cart/checkout. */
export function StickyActionBar({ eyebrow, title, value, note, children }: StickyActionBarProps) {
  return (
    <>
      <div className="sticky-action-spacer sticky-action-spacer--stack" aria-hidden="true" />
      <section className="sticky-action-bar sticky-action-bar--stack" aria-label="Actions">
        <div className="sticky-action-copy">
          {eyebrow ? <p className="eyebrow">{eyebrow}</p> : null}
          <div className="sticky-action-row">
            <div>
              <h2>{title}</h2>
              {note ? <p>{note}</p> : null}
            </div>
            <strong>{value}</strong>
          </div>
        </div>
        <div className="sticky-action-cta">{children}</div>
      </section>
    </>
  );
}
