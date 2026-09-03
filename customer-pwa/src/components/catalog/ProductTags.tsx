import {
  useEffect,
  useId,
  useLayoutEffect,
  useMemo,
  useRef,
  useState,
} from 'react';
import { createPortal } from 'react-dom';
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

const POPOVER_EVENT = 'product-tags-popover-open';

function styleClass(style: string | undefined): string {
  if (!style) {
    return 'is-style-muted';
  }

  return STYLE_CLASS[style] ?? 'is-style-muted';
}

interface PopoverPosition {
  top: number;
  left: number;
}

function clampPopoverPosition(
  anchor: DOMRect,
  width: number,
  height: number,
): PopoverPosition {
  const margin = 8;
  const gap = 6;
  const viewportWidth = window.innerWidth;
  const viewportHeight = window.innerHeight;

  let top = anchor.top - height - gap;
  if (top < margin) {
    top = anchor.bottom + gap;
  }
  if (top + height > viewportHeight - margin) {
    top = Math.max(margin, viewportHeight - height - margin);
  }

  // Prefer aligning to the badge’s right edge (inward from the right).
  let left = anchor.right - width;
  if (left < margin) {
    left = margin;
  }
  if (left + width > viewportWidth - margin) {
    left = Math.max(margin, viewportWidth - width - margin);
  }

  return { top, left };
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
  const popoverId = useId();
  const instanceId = useId();
  const railRef = useRef<HTMLDivElement>(null);
  const measureRef = useRef<HTMLDivElement>(null);
  const moreButtonRef = useRef<HTMLButtonElement>(null);
  const popoverRef = useRef<HTMLDivElement>(null);
  const [open, setOpen] = useState(false);
  const [fitCount, setFitCount] = useState(maxVisible);
  const [position, setPosition] = useState<PopoverPosition>({ top: 0, left: 0 });
  const [positionReady, setPositionReady] = useState(false);

  const marketingTags = useMemo(
    () => (Array.isArray(tags) ? tags : []).filter((tag) => Boolean(tag.key) && Boolean(tag.label)),
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
    setOpen(false);
    setPositionReady(false);
  }, [tagSignature]);

  useLayoutEffect(() => {
    if (!isCompact || allTags.length === 0) {
      setFitCount(allTags.length);
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
  }, [allTags.length, isCompact, maxVisible, tagSignature]);

  const updatePosition = (): void => {
    const anchor = moreButtonRef.current;
    const popover = popoverRef.current;
    if (!anchor || !popover) {
      return;
    }

    setPosition(clampPopoverPosition(anchor.getBoundingClientRect(), popover.offsetWidth, popover.offsetHeight));
  };

  useLayoutEffect(() => {
    if (!open) {
      setPositionReady(false);
      return;
    }

    updatePosition();
    setPositionReady(true);
  }, [open, tagSignature]);

  useEffect(() => {
    if (!open) {
      return;
    }

    window.dispatchEvent(
      new CustomEvent(POPOVER_EVENT, {
        detail: { id: instanceId },
      }),
    );

    const handlePeerOpen = (event: Event): void => {
      const detail = (event as CustomEvent<{ id: string }>).detail;
      if (detail?.id !== instanceId) {
        setOpen(false);
      }
    };

    const handlePointerDown = (event: PointerEvent): void => {
      const target = event.target as Node | null;
      if (!target) {
        return;
      }

      if (moreButtonRef.current?.contains(target) || popoverRef.current?.contains(target)) {
        return;
      }

      setOpen(false);
    };

    const handleKeyDown = (event: KeyboardEvent): void => {
      if (event.key === 'Escape') {
        setOpen(false);
        moreButtonRef.current?.focus();
      }
    };

    const handleDismissOnScroll = (): void => {
      setOpen(false);
    };

    const handleResize = (): void => {
      updatePosition();
    };

    window.addEventListener(POPOVER_EVENT, handlePeerOpen);
    document.addEventListener('pointerdown', handlePointerDown, true);
    document.addEventListener('keydown', handleKeyDown);
    window.addEventListener('scroll', handleDismissOnScroll, true);
    window.addEventListener('resize', handleResize);

    return () => {
      window.removeEventListener(POPOVER_EVENT, handlePeerOpen);
      document.removeEventListener('pointerdown', handlePointerDown, true);
      document.removeEventListener('keydown', handleKeyDown);
      window.removeEventListener('scroll', handleDismissOnScroll, true);
      window.removeEventListener('resize', handleResize);
    };
  }, [instanceId, open]);

  if (allTags.length === 0) {
    return null;
  }

  const visibleCount = isCompact ? Math.min(fitCount, maxVisible, allTags.length) : allTags.length;
  const visible = allTags.slice(0, visibleCount);
  const hidden = isCompact ? allTags.slice(visibleCount) : [];
  const showToggle = isCompact && hidden.length > 0;

  return (
    <div className={`product-tags-shell ${className}`.trim()}>
      {isCompact ? (
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
        className={`product-tags product-tags-${mode}`}
        aria-label="Product tags"
      >
        {visible.map((tag) => (
          <span key={tag.key} className={`product-tag ${styleClass(tag.style)}`}>
            {tag.label}
          </span>
        ))}

        {showToggle ? (
          <button
            ref={moreButtonRef}
            type="button"
            className={`product-tag product-tag-more ${open ? 'is-open' : ''}`.trim()}
            aria-expanded={open}
            aria-haspopup="dialog"
            aria-controls={popoverId}
            aria-label={`Show ${hidden.length} more tag${hidden.length === 1 ? '' : 's'}`}
            onClick={(event) => {
              event.preventDefault();
              event.stopPropagation();
              setOpen((current) => !current);
            }}
          >
            +{hidden.length}
          </button>
        ) : null}
      </div>

      {open && showToggle
        ? createPortal(
            <div
              ref={popoverRef}
              id={popoverId}
              className={`product-tags-popover ${positionReady ? 'is-ready' : ''}`.trim()}
              role="dialog"
              aria-label={`${hidden.length} more product tag${hidden.length === 1 ? '' : 's'}`}
              style={{ top: position.top, left: position.left }}
              onClick={(event) => {
                event.stopPropagation();
              }}
            >
              <ul className="product-tags-popover-list" role="list">
                {hidden.map((tag) => (
                  <li key={tag.key} className={`product-tag ${styleClass(tag.style)}`}>
                    {tag.label}
                  </li>
                ))}
              </ul>
            </div>,
            document.body,
          )
        : null}
    </div>
  );
}
