import { useId, useLayoutEffect, useMemo, useRef, useState } from 'react';
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
 * Marketing tags from the API. Compact mode shows fit-aware priority pills + overflow.
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
  const railRef = useRef<HTMLDivElement>(null);
  const measureRef = useRef<HTMLDivElement>(null);
  const [expanded, setExpanded] = useState(false);
  const [fitCount, setFitCount] = useState(maxVisible);

  const marketingTags = useMemo(
    () => (tags ?? []).filter((tag) => Boolean(tag.key) && Boolean(tag.label)),
    [tags],
  );

  const allTags = useMemo(() => {
    const next = [...marketingTags];
    if (showCustomizable && isCustomizable) {
      next.push({ key: 'customizable', label: 'Custom', style: 'muted' });
    }
    return next;
  }, [marketingTags, showCustomizable, isCustomizable]);

  const tagSignature = useMemo(
    () => allTags.map((tag) => `${tag.key}:${tag.label}`).join('|'),
    [allTags],
  );

  const isCompact = mode === 'compact';

  useLayoutEffect(() => {
    setExpanded(false);
  }, [tagSignature]);

  useLayoutEffect(() => {
    if (!isCompact || allTags.length === 0 || expanded) {
      if (!isCompact || allTags.length === 0) {
        setFitCount(allTags.length);
      }
      return;
    }

    const rail = railRef.current;
    const measure = measureRef.current;
    if (!rail || !measure) {
      return;
    }

    const updateFit = (): void => {
      const budget = rail.clientWidth;
      if (budget <= 0) {
        return;
      }

      const pills = Array.from(measure.querySelectorAll<HTMLElement>('[data-tag-measure]'));
      const moreEl = measure.querySelector<HTMLElement>('[data-more-measure]');
      const styles = getComputedStyle(measure);
      const gap = Number.parseFloat(styles.columnGap || styles.gap || '3.2') || 3.2;
      const max = Math.min(maxVisible, allTags.length);

      let next = 0;
      for (let count = max; count >= 0; count -= 1) {
        const hidden = allTags.length - count;
        let width = 0;

        for (let index = 0; index < count; index += 1) {
          width += pills[index]?.offsetWidth ?? 0;
          if (index > 0) {
            width += gap;
          }
        }

        if (hidden > 0 && moreEl) {
          moreEl.textContent = `+${hidden}`;
          if (count > 0) {
            width += gap;
          }
          width += moreEl.offsetWidth;
        }

        if (width <= budget + 0.5) {
          next = count;
          break;
        }
      }

      setFitCount(next);
    };

    updateFit();

    const observer = new ResizeObserver(() => {
      updateFit();
    });
    observer.observe(rail);

    return () => {
      observer.disconnect();
    };
  }, [allTags.length, expanded, isCompact, maxVisible, tagSignature]);

  if (allTags.length === 0) {
    return null;
  }

  const collapsedVisibleCount = Math.min(fitCount, maxVisible, allTags.length);
  const hiddenCount = Math.max(0, allTags.length - collapsedVisibleCount);
  const visible = isCompact && !expanded ? allTags.slice(0, collapsedVisibleCount) : allTags;
  const showToggle = isCompact && hiddenCount > 0;

  return (
    <div
      className={`product-tags-shell ${expanded ? 'is-expanded' : ''} ${className}`.trim()}
    >
      {isCompact && !expanded ? (
        <div ref={measureRef} className="product-tags product-tags-measure" aria-hidden="true">
          {allTags.map((tag) => (
            <span key={tag.key} data-tag-measure className={`product-tag ${styleClass(tag.style)}`}>
              {tag.label}
            </span>
          ))}
          <span data-more-measure className="product-tag product-tag-more">
            +{allTags.length}
          </span>
        </div>
      ) : null}

      <div
        ref={railRef}
        id={listId}
        className={`product-tags product-tags-${mode} ${expanded ? 'is-expanded' : ''}`.trim()}
        aria-label="Product tags"
      >
        {visible.map((tag) => (
          <span key={tag.key} className={`product-tag ${styleClass(tag.style)}`}>
            {tag.label}
          </span>
        ))}

        {showToggle ? (
          <button
            type="button"
            className={`product-tag product-tag-more ${expanded ? 'is-collapse' : ''}`.trim()}
            aria-expanded={expanded}
            aria-controls={listId}
            aria-label={
              expanded
                ? `Hide ${hiddenCount} extra tags`
                : `Show ${hiddenCount} more tag${hiddenCount === 1 ? '' : 's'}`
            }
            onClick={(event) => {
              event.preventDefault();
              event.stopPropagation();
              setExpanded((open) => !open);
            }}
          >
            {expanded ? 'Less' : `+${hiddenCount}`}
          </button>
        ) : null}
      </div>
    </div>
  );
}
