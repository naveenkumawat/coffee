import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { ApiError } from '../api/client';
import {
  DiningDraftItem,
  DiningRound,
  DiningSession,
  callWaiter,
  cancelWaiterCall,
  clearDiningDrafts,
  fetchDiningSession,
  placeDiningRound,
  removeDiningDraft,
  requestDiningBill,
  updateDiningDraft,
} from '../api/dining';
import { CompactDiningRoundBar } from '../components/common/CompactActionBars';
import { QuantityStepper } from '../components/common/QuantityStepper';
import { useDiningOpsSync } from '../notifications/useDiningOpsSync';
import { useLiveCanonicalSync } from '../notifications/useLiveCanonicalSync';
import { formatCurrency, formatDateTime } from '../utils/format';
import { AppIcons } from '../utils/icons';
import {
  clearOrderingContext,
  diningDraftItemCount,
  diningMenuPath,
  isDiningSessionTerminal,
  writeOrderingContext,
} from '../utils/orderingContext';

function draftAddOnSummary(draft: DiningDraftItem): string {
  const parts = (draft.add_ons ?? [])
    .filter((addOn) => addOn.quantity > 0)
    .map((addOn) => (addOn.quantity > 1 ? `${addOn.name} ×${addOn.quantity}` : String(addOn.name ?? '')))
    .filter(Boolean);

  return parts.join(' · ');
}

function roundStatusTone(round: DiningRound): string {
  const label = String(round.status_label ?? round.status ?? '').toLowerCase();

  if (round.served || label.includes('served')) {
    return 'is-success';
  }

  if (label.includes('cancel') || label.includes('reject')) {
    return 'is-danger';
  }

  if (label.includes('ready')) {
    return 'is-ready';
  }

  if (label.includes('prepar') || label.includes('accepted')) {
    return 'is-pending';
  }

  return 'is-pending';
}

function customerRoundStatus(round: DiningRound): string {
  if (round.served) {
    return 'Served';
  }

  return String(round.status_label ?? round.status ?? 'Placed');
}

function sortRoundsNewestFirst(rounds: DiningRound[]): Array<DiningRound & { displayNumber: number }> {
  const ascending = [...rounds].sort((left, right) => {
    const leftNumber = Number(left.dining_round_number ?? 0);
    const rightNumber = Number(right.dining_round_number ?? 0);

    if (leftNumber !== rightNumber && leftNumber > 0 && rightNumber > 0) {
      return leftNumber - rightNumber;
    }

    return Number(left.id) - Number(right.id);
  });

  return ascending
    .map((round, index) => ({
      ...round,
      displayNumber: Number(round.dining_round_number ?? index + 1),
    }))
    .reverse();
}

function formatPlacedTime(value: string | null | undefined): string | null {
  if (!value) {
    return null;
  }

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return null;
  }

  return new Intl.DateTimeFormat('en-IN', {
    hour: 'numeric',
    minute: '2-digit',
  }).format(date);
}

export function DiningSessionPage() {
  const { sessionId = '' } = useParams();
  const navigate = useNavigate();
  const [session, setSession] = useState<DiningSession | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [busyDraftId, setBusyDraftId] = useState<number | null>(null);
  const [expandedRoundIds, setExpandedRoundIds] = useState<number[]>([]);
  const leavingTerminalRef = useRef(false);

  const leaveCompletedSession = useCallback((): void => {
    if (leavingTerminalRef.current) {
      return;
    }

    leavingTerminalRef.current = true;
    clearOrderingContext();
    navigate('/dining', { replace: true });
  }, [navigate]);

  const applySession = useCallback(
    (next: DiningSession): void => {
      if (isDiningSessionTerminal(next)) {
        leaveCompletedSession();

        return;
      }

      setSession(next);
      writeOrderingContext({
        type: 'dining',
        diningSessionId: String(next.id),
        tableLabel: next.table.label,
        draftItemCount: diningDraftItemCount(next.drafts),
      });
    },
    [leaveCompletedSession],
  );

  const reload = useCallback(async (): Promise<void> => {
    const response = await fetchDiningSession(sessionId);
    applySession(response.data);
  }, [applySession, sessionId]);

  useLiveCanonicalSync(
    () => {
      void reload().catch(() => undefined);
    },
    (signal) => {
      if (signal.subject?.type === 'DiningSession' && String(signal.subject.id) === String(sessionId)) {
        return true;
      }

      if (signal.type.startsWith('customer.dining') || signal.type.startsWith('customer.payment')) {
        return Boolean(signal.action_url && signal.action_url.includes(`/dining/sessions/${sessionId}`));
      }

      return false;
    },
  );

  useDiningOpsSync(
    () => {
      void reload().catch(() => undefined);
    },
    (payload) => String(payload.session_id) === String(sessionId),
    { sessionId },
  );

  useEffect(() => {
    leavingTerminalRef.current = false;
    writeOrderingContext({ type: 'dining', diningSessionId: sessionId });

    let cancelled = false;

    void (async () => {
      try {
        const response = await fetchDiningSession(sessionId);
        if (!cancelled) {
          applySession(response.data);
        }
      } catch (err) {
        if (!cancelled) {
          setError(err instanceof ApiError ? err.message : 'Unable to load dining session.');
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [applySession, sessionId]);

  async function run(action: () => Promise<void>): Promise<void> {
    setBusy(true);
    setError(null);
    try {
      await action();
      await reload();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Action failed.');
    } finally {
      setBusy(false);
    }
  }

  const drafts = session?.drafts ?? [];
  const draftCount = drafts.reduce((sum, draft) => sum + draft.quantity, 0);
  const draftTotal = useMemo(
    () => drafts.reduce((sum, draft) => sum + Number(draft.line_total ?? 0), 0).toFixed(2),
    [drafts],
  );
  const rounds = useMemo(() => sortRoundsNewestFirst(session?.rounds ?? []), [session?.rounds]);
  const billTotal = session?.totals?.total ?? session?.running_bill?.total ?? '0.00';
  const canOrder = session?.capabilities?.can_add_rounds ?? session?.status === 'open';
  const canCallWaiter = Boolean(session?.capabilities?.can_call_waiter) && canOrder;
  const serviceRequest = session?.service_request ?? null;
  const totalRoundItems = rounds.reduce(
    (sum, round) => sum + (round.items ?? []).reduce((inner, item) => inner + Number(item.quantity ?? 0), 0),
    0,
  );

  async function handleQuantityChange(draft: DiningDraftItem, quantity: number): Promise<void> {
    setBusyDraftId(draft.id);
    setError(null);

    try {
      if (quantity <= 0) {
        await removeDiningDraft(sessionId, draft.id);
      } else {
        await updateDiningDraft(sessionId, draft.id, { quantity });
      }
      await reload();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Unable to update item.');
    } finally {
      setBusyDraftId(null);
    }
  }

  async function handleClearRound(): Promise<void> {
    if (drafts.length === 0) {
      return;
    }

    const needsConfirm =
      drafts.length > 1 || drafts.some((draft) => (draft.add_ons?.length ?? 0) > 0 || draft.quantity > 1);

    if (needsConfirm && !window.confirm('Clear your next round? This removes all items you have not placed yet.')) {
      return;
    }

    await run(async () => {
      await clearDiningDrafts(sessionId);
    });
  }

  async function handlePlaceOrder(): Promise<void> {
    if (drafts.length === 0) {
      return;
    }

    await run(async () => {
      await placeDiningRound(sessionId);
    });
  }

  async function handleRequestBill(): Promise<void> {
    if (rounds.length === 0) {
      return;
    }

    const confirmed = window.confirm(
      'Ready to request your bill?\nYou won’t be able to add another round after the bill is requested.',
    );

    if (!confirmed) {
      return;
    }

    await run(async () => {
      await requestDiningBill(sessionId);
      clearOrderingContext();
      navigate(`/dining/sessions/${sessionId}/bill`);
    });
  }

  function toggleRound(roundId: number): void {
    setExpandedRoundIds((current) =>
      current.includes(roundId) ? current.filter((id) => id !== roundId) : [...current, roundId],
    );
  }

  if (!session) {
    return (
      <div className="page-container dining-session-page">
        <div className="dining-content">
          <h1 className="visually-hidden">Dining session</h1>
          {error ? <p className="form-error-text">{error}</p> : <p className="muted">Loading…</p>}
        </div>
      </div>
    );
  }

  const statusLabel = session.status_label ?? session.status;

  return (
    <div
      className={`page-container dining-session-page${drafts.length > 0 && canOrder ? ' has-sticky-cta' : ''}`.trim()}
    >
      <div className="dining-content">
      <header className="dining-session-hero" aria-labelledby="dining-session-table">
        <div className="dining-session-hero-top">
          <div>
            <p className="dining-session-kicker">Dining session</p>
            <h1 id="dining-session-table" className="dining-session-table">
              Table {session.table.label}
            </h1>
            <p className="dining-session-ref muted">{session.session_number}</p>
          </div>
          <span className={`status-badge dining-session-status ${canOrder ? 'is-success' : 'is-pending'}`}>
            {statusLabel}
          </span>
        </div>
        <div className="dining-session-bill-row">
          <span>Running bill</span>
          <strong>{formatCurrency(billTotal)}</strong>
        </div>
      </header>

      {error ? (
        <p className="form-error-text" role="alert">
          {error}
        </p>
      ) : null}

      {canCallWaiter || serviceRequest ? (
        <section className="dining-waiter-call" aria-label="Call a waiter">
          {serviceRequest && (serviceRequest.status === 'pending' || serviceRequest.status === 'claimed') ? (
            <div className="dining-waiter-call-status" role="status">
              <i
                className={`bi ${serviceRequest.status === 'claimed' ? AppIcons.check : AppIcons.notification}`}
                aria-hidden="true"
              ></i>
              <div>
                <strong>
                  {serviceRequest.status === 'claimed' ? 'A waiter is on the way' : 'Waiter called'}
                </strong>
                <p className="muted mb-0">
                  {serviceRequest.customer_message ??
                    (serviceRequest.status === 'claimed'
                      ? 'A waiter is on the way.'
                      : 'We’ve notified a waiter.')}
                </p>
              </div>
              {serviceRequest.status === 'pending' ? (
                <button
                  type="button"
                  className="btn btn-sm btn-outline-dark rounded-pill"
                  disabled={busy}
                  onClick={() =>
                    void run(async () => {
                      await cancelWaiterCall(serviceRequest.id);
                    })
                  }
                >
                  Cancel
                </button>
              ) : null}
            </div>
          ) : (
            <button
              type="button"
              className="btn btn-outline-dark rounded-pill dining-waiter-call-btn"
              aria-label="Call a waiter"
              disabled={busy}
              onClick={() =>
                void run(async () => {
                  await callWaiter(sessionId);
                })
              }
            >
              <i className={`bi ${AppIcons.notification}`} aria-hidden="true"></i>
              Call waiter
            </button>
          )}
        </section>
      ) : null}

      {canOrder ? (
        <section className="dining-order-more" aria-labelledby="dining-order-more-title">
          <div className="dining-section-head">
            <h2 id="dining-order-more-title">Order more</h2>
            {draftCount > 0 ? (
              <span className="dining-pill">
                {draftCount} item{draftCount === 1 ? '' : 's'} in next round
              </span>
            ) : null}
          </div>

          {drafts.length === 0 ? (
            <div className="dining-empty-draft">
              <p className="muted">Add anything you’d like for your next round.</p>
              <Link
                to={diningMenuPath(sessionId)}
                className="btn btn-primary rounded-pill"
                onClick={() =>
                  writeOrderingContext({
                    type: 'dining',
                    diningSessionId: sessionId,
                    tableLabel: session.table.label,
                    draftItemCount: diningDraftItemCount(session.drafts),
                  })
                }
              >
                <i className="bi bi-plus-lg" aria-hidden="true"></i>
                Add items
              </Link>
            </div>
          ) : (
            <>
              <div className="dining-section-head dining-next-round-head">
                <h3>Your next round</h3>
                <Link
                  to={diningMenuPath(sessionId)}
                  className="btn btn-sm btn-outline-dark rounded-pill"
                  onClick={() =>
                    writeOrderingContext({
                      type: 'dining',
                      diningSessionId: sessionId,
                      tableLabel: session.table.label,
                      draftItemCount: diningDraftItemCount(session.drafts),
                    })
                  }
                >
                  <i className="bi bi-plus-lg" aria-hidden="true"></i>
                  Add items
                </Link>
              </div>

              <ul className="dining-draft-list">
                {drafts.map((draft) => {
                  const addOnText = draftAddOnSummary(draft);

                  return (
                    <li key={draft.id} className="dining-draft-card">
                      <div className="dining-draft-card-main">
                        <div>
                          <strong className="dining-draft-name">{draft.product_name}</strong>
                          <p className="dining-draft-meta muted">
                            {[draft.variant_name, addOnText].filter(Boolean).join(' · ')}
                          </p>
                        </div>
                        <strong className="dining-draft-total">{formatCurrency(draft.line_total)}</strong>
                      </div>
                      <div className="dining-draft-card-actions">
                        <QuantityStepper
                          value={draft.quantity}
                          size="sm"
                          allowRemove
                          disabled={busy || busyDraftId === draft.id}
                          onChange={(next) => void handleQuantityChange(draft, next)}
                        />
                        <button
                          type="button"
                          className="btn btn-text dining-draft-remove"
                          disabled={busy || busyDraftId === draft.id}
                          onClick={() => void handleQuantityChange(draft, 0)}
                        >
                          Remove
                        </button>
                      </div>
                    </li>
                  );
                })}
              </ul>

              <div className="dining-round-total-row">
                <span>Round total</span>
                <strong>{formatCurrency(draftTotal)}</strong>
              </div>

              <div className="dining-place-inline">
                <p className="muted dining-place-note">
                  Use Place order below when you are ready. Items will be sent to the café for preparation.
                </p>
                <button
                  type="button"
                  className="btn btn-text dining-clear-round"
                  disabled={busy || busyDraftId !== null}
                  onClick={() => void handleClearRound()}
                >
                  Clear round
                </button>
              </div>
            </>
          )}
        </section>
      ) : (
        <section className="dining-bill-requested" aria-live="polite">
          <h2>Bill requested</h2>
          <p className="muted">Your bill is being prepared. You can’t add another round right now.</p>
          <Link className="btn btn-primary rounded-pill" to={`/dining/sessions/${sessionId}/bill`}>
            Open bill / payment
          </Link>
        </section>
      )}

      <section className="dining-orders" aria-labelledby="dining-orders-title">
        <div className="dining-section-head">
          <h2 id="dining-orders-title">Your orders</h2>
        </div>

        {rounds.length === 0 ? <p className="muted">No orders placed yet.</p> : null}

        <div className="dining-round-list">
          {rounds.map((round) => {
            const expanded = expandedRoundIds.includes(round.id);
            const itemCount = (round.items ?? []).reduce((sum, item) => sum + Number(item.quantity ?? 0), 0);
            const placed = formatPlacedTime(round.placed_at);

            return (
              <article key={round.id} className="dining-round-card">
                <div className="dining-round-card-top">
                  <div>
                    <strong>Round {round.displayNumber}</strong>
                    <p className="muted mb-0">
                      {itemCount} item{itemCount === 1 ? '' : 's'}
                      {placed ? ` · Placed ${placed}` : ''}
                    </p>
                  </div>
                  <div className="dining-round-card-end">
                    <span className={`status-badge ${roundStatusTone(round)}`}>{customerRoundStatus(round)}</span>
                    <strong>{formatCurrency(round.total_amount)}</strong>
                  </div>
                </div>

                <button
                  type="button"
                  className="btn btn-text dining-round-toggle"
                  aria-expanded={expanded}
                  onClick={() => toggleRound(round.id)}
                >
                  {expanded ? 'Hide details' : 'View details'}
                </button>

                {expanded ? (
                  <div className="dining-round-details">
                    {round.order_number ? (
                      <p className="dining-round-ref muted">Order {round.order_number}</p>
                    ) : null}
                    <ul className="dining-round-items">
                      {(round.items ?? []).map((item) => {
                        const addOns = (item.add_ons ?? [])
                          .map((addOn) =>
                            addOn.quantity > 1 ? `${addOn.name} ×${addOn.quantity}` : String(addOn.name ?? ''),
                          )
                          .filter(Boolean)
                          .join(', ');

                        return (
                          <li key={item.id}>
                            <span>
                              {item.product_name}
                              {item.variant_name ? ` · ${item.variant_name}` : ''}
                              {addOns ? ` · ${addOns}` : ''}
                            </span>
                            <span>×{item.quantity}</span>
                          </li>
                        );
                      })}
                    </ul>
                    {round.placed_at ? (
                      <p className="muted dining-round-placed">{formatDateTime(round.placed_at)}</p>
                    ) : null}
                  </div>
                ) : null}
              </article>
            );
          })}
        </div>
      </section>

      {canOrder && rounds.length > 0 ? (
        <section className="dining-finish-card" aria-labelledby="dining-finish-title">
          <h2 id="dining-finish-title">Ready to finish?</h2>
          <div className="dining-finish-bill">
            <div>
              <span className="muted">Running bill</span>
              <p className="dining-finish-summary muted mb-0">
                {rounds.length} round{rounds.length === 1 ? '' : 's'}
                {totalRoundItems > 0 ? ` · ${totalRoundItems} item${totalRoundItems === 1 ? '' : 's'}` : ''}
              </p>
            </div>
            <strong>{formatCurrency(billTotal)}</strong>
          </div>
          <button
            type="button"
            className="btn btn-primary rounded-pill w-100"
            disabled={busy}
            onClick={() => void handleRequestBill()}
          >
            Request bill
          </button>
        </section>
      ) : null}

      </div>

      {canOrder && drafts.length > 0 ? (
        <CompactDiningRoundBar
          itemCount={draftCount}
          totalLabel={formatCurrency(draftTotal)}
          ctaLabel="Place order"
          disabled={busy || busyDraftId !== null}
          onPlaceOrder={() => void handlePlaceOrder()}
        />
      ) : null}
    </div>
  );
}
