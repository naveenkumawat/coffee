import { RefObject, useLayoutEffect, useRef } from 'react';
import {
  lockOverlayBackgroundScroll,
  unlockOverlayBackgroundScroll,
} from '../utils/overlayScrollLock';

interface UseProductOverlayOptions {
  open: boolean;
  historyKey: string;
  onClose: () => void;
  focusRef: RefObject<HTMLElement | null>;
}

/** Top-of-stack key for nested product overlays (detail → customize). */
const overlayStack: string[] = [];

/**
 * Shared overlay chrome: nested-safe background scroll lock, focus restore,
 * Escape, and backdrop-safe history Back.
 *
 * Critical lock/unlock runs in useLayoutEffect so unlock + viewport restore
 * happen before paint (no top → previous-position flicker).
 *
 * Overlay open is UI state only — same URL, no router navigation.
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
  const onCloseRef = useRef(onClose);
  onCloseRef.current = onClose;

  useLayoutEffect(() => {
    if (!open) {
      return;
    }

    previouslyFocusedRef.current =
      document.activeElement instanceof HTMLElement ? document.activeElement : null;

    lockOverlayBackgroundScroll();

    // Focus after lock, before paint — never scroll the page into view.
    focusRef.current?.focus({ preventScroll: true });

    closedByPopRef.current = false;
    const alreadyOurs = window.history.state?.productOverlay === historyKey;

    if (!alreadyOurs) {
      pushedHistoryRef.current = true;
      // Same URL — no route/hash change; enables hardware Back to dismiss.
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
      onCloseRef.current();
    };

    const handleKeyDown = (event: KeyboardEvent): void => {
      if (event.key !== 'Escape') {
        return;
      }

      if (overlayStack[overlayStack.length - 1] !== historyKey) {
        return;
      }

      event.preventDefault();
      onCloseRef.current();
    };

    window.addEventListener('popstate', handlePopState);
    window.addEventListener('keydown', handleKeyDown);

    return () => {
      window.removeEventListener('popstate', handlePopState);
      window.removeEventListener('keydown', handleKeyDown);

      const stackIndex = overlayStack.lastIndexOf(historyKey);

      if (stackIndex >= 0) {
        overlayStack.splice(stackIndex, 1);
      }

      // Pop our history entry while still locked so any browser scroll work
      // happens under the fixed-body offset, then unlock atomically.
      if (pushedHistoryRef.current && !closedByPopRef.current) {
        pushedHistoryRef.current = false;
        window.history.back();
      }

      unlockOverlayBackgroundScroll();

      const trigger = previouslyFocusedRef.current;
      previouslyFocusedRef.current = null;

      // Only restore focus when the element is still in the document and
      // visible enough that preventScroll can keep the viewport stable.
      if (trigger && document.contains(trigger)) {
        trigger.focus({ preventScroll: true });
      }
    };
  }, [open, historyKey, focusRef]);
}
