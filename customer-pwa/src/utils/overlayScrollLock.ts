/**
 * Nested-safe background scroll lock for product overlays.
 *
 * Owns ONE lock for the whole overlay stack. Nested sheets must not
 * recapture scrollY or unlock early.
 *
 * Scrolling element: window / document (app-shell grows with content;
 * there is no internal #root / .app-main overflow scroller).
 *
 * Unlock + viewport restore MUST run in the same synchronous turn
 * (prefer useLayoutEffect cleanup) so the browser never paints the
 * unlocked top-of-page intermediate state.
 */

type StyleSnapshot = {
  overflow: string;
  position: string;
  top: string;
  left: string;
  right: string;
  width: string;
  htmlOverflow: string;
};

let lockDepth = 0;
let lockedScrollY = 0;
let previous: StyleSnapshot | null = null;

export function getOverlayScrollLockDepth(): number {
  return lockDepth;
}

export function getOverlayLockedScrollY(): number {
  return lockedScrollY;
}

export function lockOverlayBackgroundScroll(): void {
  if (typeof document === 'undefined') {
    return;
  }

  if (lockDepth === 0) {
    lockedScrollY = window.scrollY || window.pageYOffset || 0;

    const body = document.body.style;
    const html = document.documentElement.style;

    previous = {
      overflow: body.overflow,
      position: body.position,
      top: body.top,
      left: body.left,
      right: body.right,
      width: body.width,
      htmlOverflow: html.overflow,
    };

    html.overflow = 'hidden';
    body.overflow = 'hidden';
    body.position = 'fixed';
    body.top = `-${lockedScrollY}px`;
    body.left = '0';
    body.right = '0';
    body.width = '100%';
    document.body.classList.add('product-overlay-open');
  }

  lockDepth += 1;
}

/**
 * Decrements lock depth. On the last unlock, clears fixed-body styles and
 * restores the captured scroll offset in the same turn (no rAF / timeout).
 */
export function unlockOverlayBackgroundScroll(): void {
  if (typeof document === 'undefined') {
    return;
  }

  lockDepth = Math.max(0, lockDepth - 1);

  if (lockDepth > 0 || previous === null) {
    return;
  }

  const body = document.body.style;
  const html = document.documentElement.style;
  const y = lockedScrollY;
  const snapshot = previous;
  previous = null;

  html.overflow = snapshot.htmlOverflow;
  body.overflow = snapshot.overflow;
  body.position = snapshot.position;
  body.top = snapshot.top;
  body.left = snapshot.left;
  body.right = snapshot.right;
  body.width = snapshot.width;
  document.body.classList.remove('product-overlay-open');

  // Same synchronous turn as style clear — before the browser can paint.
  // Assign both window and root scrollTop so engines agree without a second frame.
  window.scrollTo(0, y);
  document.documentElement.scrollTop = y;
  document.body.scrollTop = y;
}
