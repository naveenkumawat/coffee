import { RefObject, useEffect, useRef } from 'react';

interface UseProductOverlayOptions {
  open: boolean;
  historyKey: string;
  onClose: () => void;
  focusRef: RefObject<HTMLElement | null>;
}


/**
 * Shared overlay chrome: body scroll lock, focus restore, Escape,
 * backdrop-safe history Back, and prefers-reduced-motion-friendly lifecycle.
 */
export function useProductOverlay({
  open,
  historyKey,
  onClose,
  focusRef,
}: UseProductOverlayOptions): void {
  const previouslyFocusedRef = useRef<HTMLElement | null>(null);
  const pushedHistoryRef = useRef(false);
  const closedByPopRef = useRef(false);

  useEffect(() => {
    if (!open) {
      return;
    }

    previouslyFocusedRef.current = document.activeElement instanceof HTMLElement
      ? document.activeElement
      : null;

    const frame = window.requestAnimationFrame(() => {
      focusRef.current?.focus();
    });

    const scrollY = window.scrollY;
    const { style } = document.body;
    const previousOverflow = style.overflow;
    const previousPosition = style.position;
    const previousTop = style.top;
    const previousWidth = style.width;

    style.overflow = 'hidden';
    style.position = 'fixed';
    style.top = `-${scrollY}px`;
    style.width = '100%';
    document.body.classList.add('product-overlay-open');

    closedByPopRef.current = false;
    const alreadyOurs = window.history.state?.productOverlay === historyKey;

    if (!alreadyOurs) {
      pushedHistoryRef.current = true;
      window.history.pushState({ productOverlay: historyKey }, '');
    }

    const handlePopState = (): void => {
      closedByPopRef.current = true;
      pushedHistoryRef.current = false;
      onClose();
    };

    const handleKeyDown = (event: KeyboardEvent): void => {
      if (event.key === 'Escape') {
        event.preventDefault();
        onClose();
      }
    };

    window.addEventListener('popstate', handlePopState);
    window.addEventListener('keydown', handleKeyDown);

    return () => {
      window.cancelAnimationFrame(frame);
      window.removeEventListener('popstate', handlePopState);
      window.removeEventListener('keydown', handleKeyDown);

      style.overflow = previousOverflow;
      style.position = previousPosition;
      style.top = previousTop;
      style.width = previousWidth;
      document.body.classList.remove('product-overlay-open');
      window.scrollTo(0, scrollY);

      if (pushedHistoryRef.current && !closedByPopRef.current) {
        pushedHistoryRef.current = false;
        window.history.back();
      }

      previouslyFocusedRef.current?.focus?.();
    };
  }, [open, historyKey, onClose, focusRef]);
}
