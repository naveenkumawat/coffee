import { FormEvent, useEffect, useId, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { lockOverlayBackgroundScroll, unlockOverlayBackgroundScroll } from '../../utils/overlayScrollLock';

export type ConfirmTone = 'primary' | 'danger' | 'success';

export interface ConfirmRequest {
  title: string;
  body: string;
  confirmLabel?: string;
  cancelLabel?: string;
  tone?: ConfirmTone;
  requireReason?: boolean;
  reasonLabel?: string;
  reasonPlaceholder?: string;
}

export type ConfirmResult =
  | { confirmed: false }
  | { confirmed: true; reason: string | null };

type PendingConfirm = ConfirmRequest & {
  resolve: (result: ConfirmResult) => void;
};

let pendingResolver: ((request: ConfirmRequest) => Promise<ConfirmResult>) | null = null;

/**
 * Designed confirmation — replaces window.confirm for business actions.
 * Mount <ConfirmDialogHost /> once (AppLayout).
 */
export function confirmAction(request: ConfirmRequest): Promise<ConfirmResult> {
  if (!pendingResolver) {
    return Promise.resolve({ confirmed: false });
  }

  return pendingResolver(request);
}

/** Convenience boolean wrapper when no reason is needed. */
export async function confirmYes(request: ConfirmRequest): Promise<boolean> {
  const result = await confirmAction(request);

  return result.confirmed;
}

export function ConfirmDialogHost() {
  const [pending, setPending] = useState<PendingConfirm | null>(null);
  const [reason, setReason] = useState('');
  const [busy, setBusy] = useState(false);
  const titleId = useId();
  const bodyId = useId();
  const reasonId = useId();
  const cancelRef = useRef<HTMLButtonElement>(null);

  useEffect(() => {
    pendingResolver = (request) =>
      new Promise<ConfirmResult>((resolve) => {
        setReason('');
        setBusy(false);
        setPending({ ...request, resolve });
      });

    return () => {
      pendingResolver = null;
    };
  }, []);

  useLayoutEffect(() => {
    if (!pending) {
      return;
    }

    lockOverlayBackgroundScroll();

    return () => {
      unlockOverlayBackgroundScroll();
    };
  }, [pending]);

  useEffect(() => {
    if (!pending) {
      return;
    }

    cancelRef.current?.focus();

    const onKeyDown = (event: KeyboardEvent): void => {
      if (event.key === 'Escape' && !busy) {
        finish({ confirmed: false });
      }
    };

    window.addEventListener('keydown', onKeyDown);

    return () => window.removeEventListener('keydown', onKeyDown);
  }, [pending, busy]);

  function finish(result: ConfirmResult): void {
    if (!pending || busy) {
      return;
    }

    const { resolve } = pending;
    setPending(null);
    setBusy(false);
    resolve(result);
  }

  function handleSubmit(event: FormEvent<HTMLFormElement>): void {
    event.preventDefault();

    if (!pending || busy) {
      return;
    }

    if (pending.requireReason && reason.trim() === '') {
      return;
    }

    setBusy(true);
    finish({
      confirmed: true,
      reason: pending.requireReason ? reason.trim() : null,
    });
  }

  if (!pending) {
    return null;
  }

  const tone = pending.tone ?? 'primary';
  const confirmClass =
    tone === 'danger' ? 'btn-danger' : tone === 'success' ? 'btn-success' : 'btn-primary';

  return createPortal(
    <div
      className="confirm-dialog-overlay"
      role="presentation"
      onClick={() => {
        if (!busy) {
          finish({ confirmed: false });
        }
      }}
    >
      <div
        className="confirm-dialog-panel"
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        aria-describedby={bodyId}
        onClick={(event) => event.stopPropagation()}
      >
        <form onSubmit={handleSubmit}>
          <header className="confirm-dialog-header">
            <h2 id={titleId}>{pending.title}</h2>
          </header>
          <div className="confirm-dialog-body">
            <p id={bodyId}>{pending.body}</p>
            {pending.requireReason ? (
              <label className="confirm-dialog-reason" htmlFor={reasonId}>
                <span>{pending.reasonLabel ?? 'Reason'}</span>
                <textarea
                  id={reasonId}
                  className="coffee-input"
                  rows={3}
                  value={reason}
                  required
                  disabled={busy}
                  placeholder={pending.reasonPlaceholder ?? 'Add a short note'}
                  onChange={(event) => setReason(event.target.value)}
                />
              </label>
            ) : null}
          </div>
          <div className="confirm-dialog-actions">
            <button
              ref={cancelRef}
              type="button"
              className="btn btn-outline-dark rounded-pill"
              disabled={busy}
              onClick={() => finish({ confirmed: false })}
            >
              {pending.cancelLabel ?? 'Cancel'}
            </button>
            <button
              type="submit"
              className={`btn ${confirmClass} rounded-pill`}
              disabled={busy || (pending.requireReason && reason.trim() === '')}
            >
              {pending.confirmLabel ?? 'Confirm'}
            </button>
          </div>
        </form>
      </div>
    </div>,
    document.body,
  );
}
