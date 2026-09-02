import { RefObject, useEffect, useRef } from 'react';

interface UseProductOverlayOptions {
  open: boolean;
  historyKey: string;
  onClose: () => void;
  focusRef: RefObject<HTMLElement | null>;
}

/** Top-of-stack key for nested product overlays (detail → customize). */
const overlayStack: string[] = [];

/**
 * Shared overlay chrome: body scroll lock, focus restore, Escape,
 * backdrop-safe history Back, and prefers-reduced-motion-friendly lifecycle.
 *
 * Nested overlays push stacked history entries. Closing the inner sheet via
 * Back/X must not close the outer sheet.
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
    const isNested = overlayStack.length > 0;

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

    overlayStack.push(historyKey);

    const handlePopState = (event: PopStateEvent): void => {
      const currentKey = (event.state as { productOverlay?: string } | null)?.productOverlay;

      // Nested child closed — our entry is still current; stay open.
      if (currentKey === historyKey) {
        return;
      }

      closedByPopRef.current = true;
      pushedHistoryRef.current = false;
      onClose();
    };

    const handleKeyDown = (event: KeyboardEvent): void => {
      if (event.key !== 'Escape') {
        return;
      }

      // Only the topmost overlay handles Escape.
      if (overlayStack[overlayStack.length - 1] !== historyKey) {
        return;
      }

      event.preventDefault();
      onClose();
    };

    window.addEventListener('popstate', handlePopState);
    window.addEventListener('keydown', handleKeyDown);

    return () => {
      window.cancelAnimationFrame(frame);
      window.removeEventListener('popstate', handlePopState);
      window.removeEventListener('keydown', handleKeyDown);

      const stackIndex = overlayStack.lastIndexOf(historyKey);

      if (stackIndex >= 0) {
        overlayStack.splice(stackIndex, 1);
      }

      // Nested overlays must not unlock body scroll while a parent remains open.
      if (!isNested && overlayStack.length === 0) {
        style.overflow = previousOverflow;
        style.position = previousPosition;
        style.top = previousTop;
        style.width = previousWidth;
        document.body.classList.remove('product-overlay-open');
        window.scrollTo(0, scrollY);
      }

      if (pushedHistoryRef.current && !closedByPopRef.current) {
        pushedHistoryRef.current = false;
        window.history.back();
      }

      if (!isNested) {
        previouslyFocusedRef.current?.focus?.();
      }
    };
  }, [open, historyKey, onClose, focusRef]);
}
