import { useId, useMemo, useState } from 'react';
import { ProductTag as ProductTagType } from '../../types/catalog';

interface ProductTagsProps {
  tags: ProductTagType[] | null | undefined;
  mode?: 'compact' | 'detail';
  /** Max visible pills in compact mode (remaining shown as +N). */
  maxVisible?: number;
  className?: string;
  showCustomizable?: boolean;
  isCustomizable?: boolean;
}

const STYLE_CLASS: Record<string, string> = {
  primary: 'is-style-primary',
  accent: 'is-style-accent',
  soft: 'is-style-soft',
  warning: 'is-style-warning',
  muted: 'is-style-muted',
};

function styleClass(style: string | undefined): string {
  if (!style) {
    return 'is-style-muted';
  }

  return STYLE_CLASS[style] ?? 'is-style-muted';
}

/**
 * Marketing tags from the API. Compact mode shows 1–2 priority pills + overflow.
 * Vegetarian/customizable are not marketing tags — customizable can appear as metadata.
 */
export function ProductTags({
  tags,
  mode = 'compact',
  maxVisible = 2,
  className = '',
  showCustomizable = false,
  isCustomizable = false,
}: ProductTagsProps) {
  const listId = useId();
  const [overflowOpen, setOverflowOpen] = useState(false);

  const marketingTags = useMemo(
    () => (tags ?? []).filter((tag) => Boolean(tag.key) && Boolean(tag.label)),
    [tags],
  );

  const extras = showCustomizable && isCustomizable
    ? [{ key: 'customizable', label: 'Custom', style: 'muted' }]
    : [];

  const allTags = [...marketingTags, ...extras];

  if (allTags.length === 0) {
    return null;
  }

  const isCompact = mode === 'compact';
  const visibleCount = isCompact ? Math.min(maxVisible, allTags.length) : allTags.length;
  const visible = allTags.slice(0, visibleCount);
  const hidden = isCompact ? allTags.slice(visibleCount) : [];

  return (
    <div
      className={`product-tags product-tags-${mode} ${className}`.trim()}
      aria-label="Product tags"
    >
      {visible.map((tag) => (
        <span key={tag.key} className={`product-tag ${styleClass(tag.style)}`}>
          {tag.label}
        </span>
      ))}

      {hidden.length > 0 ? (
        <div className="product-tag-overflow">
          <button
            type="button"
            className="product-tag product-tag-more"
            aria-expanded={overflowOpen}
            aria-controls={listId}
            onClick={(event) => {
              event.stopPropagation();
              setOverflowOpen((open) => !open);
            }}
          >
            +{hidden.length}
          </button>
          {overflowOpen ? (
            <ul id={listId} className="product-tag-overflow-list" role="list">
              {hidden.map((tag) => (
                <li key={tag.key} className={`product-tag ${styleClass(tag.style)}`}>
                  {tag.label}
                </li>
              ))}
            </ul>
          ) : null}
        </div>
      ) : null}
    </div>
  );
}
