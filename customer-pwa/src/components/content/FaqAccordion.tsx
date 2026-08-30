import { useId, useState } from 'react';
import { FaqItem } from '../../utils/contentPages';

interface FaqAccordionProps {
  items: FaqItem[];
}

export function FaqAccordion({ items }: FaqAccordionProps) {
  const baseId = useId();
  const [openId, setOpenId] = useState<string | null>(items[0]?.id ?? null);

  if (items.length === 0) {
    return null;
  }

  return (
    <div className="faq-accordion" role="list">
      {items.map((item) => {
        const isOpen = openId === item.id;
        const panelId = `${baseId}-${item.id}-panel`;
        const buttonId = `${baseId}-${item.id}-button`;

        return (
          <div key={item.id} className={`faq-item ${isOpen ? 'is-open' : ''}`.trim()} role="listitem">
            <h2 className="faq-item-heading">
              <button
                type="button"
                id={buttonId}
                className="faq-item-trigger"
                aria-expanded={isOpen}
                aria-controls={panelId}
                onClick={() => setOpenId(isOpen ? null : item.id)}
              >
                <span>{item.question}</span>
                <i className={`bi ${isOpen ? 'bi-chevron-up' : 'bi-chevron-down'}`} aria-hidden="true"></i>
              </button>
            </h2>
            <div
              id={panelId}
              role="region"
              aria-labelledby={buttonId}
              className="faq-item-panel"
              hidden={!isOpen}
            >
              <p className="content-preline">{item.answer}</p>
            </div>
          </div>
        );
      })}
    </div>
  );
}
