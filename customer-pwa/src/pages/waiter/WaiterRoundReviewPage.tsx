import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { ApiError } from '../../api/client';
import {
  WaiterDiningSession,
  WaiterDraftItem,
  fetchWaiterSession,
  placeWaiterRound,
  removeWaiterDraft,
  updateWaiterDraft,
} from '../../api/waiterDining';
import { QuantityStepper } from '../../components/common/QuantityStepper';
import { EmptyState } from '../../components/common/EmptyState';
import { ErrorState } from '../../components/common/ErrorState';
import { LoadingSkeleton } from '../../components/common/LoadingSkeleton';
import { PageHeader } from '../../components/common/PageHeader';
import { StickyActionBar } from '../../components/common/StickyActionBar';
import { useToastStore } from '../../stores/toastStore';
import { formatCurrency } from '../../utils/format';
import { rememberWaiterSession } from '../../utils/waiterSession';

function createIdempotencyKey(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID();
  }

  return `waiter-round-${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

export function WaiterRoundReviewPage() {
  const { sessionId = '' } = useParams();
  const navigate = useNavigate();
  const toastError = useToastStore((state) => state.error);
  const toastSuccess = useToastStore((state) => state.success);
  const [session, setSession] = useState<WaiterDiningSession | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [busyDraftId, setBusyDraftId] = useState<number | null>(null);
  const [isSending, setIsSending] = useState(false);
  const idempotencyKeyRef = useRef<string | null>(null);

  const loadSession = useCallback(async (): Promise<void> => {
    setIsLoading(true);
    setErrorMessage(null);

    try {
      const response = await fetchWaiterSession(sessionId);
      setSession(response.data);
      rememberWaiterSession(response.data.id);
    } catch (error) {
      setErrorMessage(error instanceof ApiError ? error.message : 'Unable to load draft.');
      setSession(null);
    } finally {
      setIsLoading(false);
    }
  }, [sessionId]);

  useEffect(() => {
    void loadSession();
  }, [loadSession]);

  const drafts = session?.drafts ?? [];
  const draftTotal = useMemo(
    () => drafts.reduce((sum, draft) => sum + Number(draft.line_total ?? 0), 0).toFixed(2),
    [drafts],
  );
  const draftCount = drafts.reduce((sum, draft) => sum + draft.quantity, 0);

  async function mutateDraft(draftId: number, action: () => Promise<WaiterDiningSession>): Promise<void> {
    setBusyDraftId(draftId);

    try {
      const next = await action();
      setSession(next);
    } catch (error) {
      toastError(error instanceof ApiError ? error.message : 'Unable to update draft.');
    } finally {
      setBusyDraftId(null);
    }
  }

  async function handleQuantityChange(draft: WaiterDraftItem, quantity: number): Promise<void> {
    if (quantity <= 0) {
      await mutateDraft(draft.id, async () => {
        const response = await removeWaiterDraft(sessionId, draft.id);

        return response.data;
      });

      return;
    }

    await mutateDraft(draft.id, async () => {
      const response = await updateWaiterDraft(sessionId, draft.id, { quantity });

      return response.data;
    });
  }

  async function handleRemove(draft: WaiterDraftItem): Promise<void> {
    await mutateDraft(draft.id, async () => {
      const response = await removeWaiterDraft(sessionId, draft.id);

      return response.data;
    });
  }

  async function handleSend(): Promise<void> {
    if (isSending || drafts.length === 0) {
      return;
    }

    if (!idempotencyKeyRef.current) {
      idempotencyKeyRef.current = createIdempotencyKey();
    }

    setIsSending(true);

    try {
      await placeWaiterRound(sessionId, {
        idempotency_key: idempotencyKeyRef.current,
      });
      idempotencyKeyRef.current = null;
      toastSuccess('Order sent');
      navigate(`/waiter/sessions/${sessionId}`, { replace: true });
    } catch (error) {
      toastError(error instanceof ApiError ? error.message : 'Unable to send order.');
    } finally {
      setIsSending(false);
    }
  }

  if (isLoading && !session) {
    return (
      <div className="page-container waiter-page">
        <PageHeader title="Review order" showBack />
        <LoadingSkeleton cardCount={3} lines={3} />
      </div>
    );
  }

  if (!session) {
    return (
      <div className="page-container waiter-page">
        <PageHeader title="Review order" showBack />
        <ErrorState description={errorMessage ?? 'Draft not found.'} onRetry={() => void loadSession()} />
      </div>
    );
  }

  return (
    <div className="page-container waiter-page has-sticky-cta">
      <PageHeader
        title="Review order"
        description={session.table.label}
        showBack
        rightSlot={
          <Link to={`/waiter/sessions/${sessionId}/menu`} className="link-button">
            Add more
          </Link>
        }
      />

      {drafts.length === 0 ? (
        <EmptyState
          title="Draft is empty"
          description="Add items from the menu before sending a round."
          actionLabel="Browse menu"
          actionHref={`/waiter/sessions/${sessionId}/menu`}
        />
      ) : (
        <ul className="waiter-review-list motion-enter">
          {drafts.map((draft) => (
            <li key={draft.id} className="waiter-review-item">
              <div className="waiter-review-copy">
                <strong>
                  {draft.product_name}
                  {draft.variant_name ? ` · ${draft.variant_name}` : ''}
                </strong>
                {(draft.add_ons?.length ?? 0) > 0 ? (
                  <p className="waiter-review-addons">
                    {draft.add_ons
                      ?.map((addOn) =>
                        addOn.quantity > 1
                          ? `${addOn.name ?? 'Add-on'} ×${addOn.quantity}`
                          : (addOn.name ?? 'Add-on'),
                      )
                      .join(', ')}
                  </p>
                ) : null}
                <strong className="waiter-review-line-total">{formatCurrency(draft.line_total)}</strong>
              </div>
              <div className="waiter-review-actions">
                <QuantityStepper
                  value={draft.quantity}
                  size="sm"
                  allowRemove
                  disabled={busyDraftId === draft.id || isSending}
                  onChange={(next) => void handleQuantityChange(draft, next)}
                />
                <button
                  type="button"
                  className="btn btn-text"
                  disabled={busyDraftId === draft.id || isSending}
                  onClick={() => void handleRemove(draft)}
                >
                  Remove
                </button>
              </div>
            </li>
          ))}
        </ul>
      )}

      <StickyActionBar
        eyebrow={session.table.label}
        title="Send to kitchen"
        value={formatCurrency(draftTotal)}
        note={
          draftCount > 0
            ? `${draftCount} item${draftCount === 1 ? '' : 's'} in this round`
            : 'Add items before sending'
        }
      >
        <div className="sticky-action-stack waiter-sticky-actions">
          <Link
            to={`/waiter/sessions/${sessionId}/menu`}
            className="btn btn-secondary btn-lg rounded-pill w-100"
          >
            Add more
          </Link>
          <button
            type="button"
            className="btn btn-primary btn-lg rounded-pill w-100"
            disabled={isSending || drafts.length === 0 || busyDraftId !== null}
            aria-busy={isSending}
            onClick={() => void handleSend()}
          >
            {isSending ? 'Sending…' : 'Send order'}
          </button>
        </div>
      </StickyActionBar>
    </div>
  );
}
