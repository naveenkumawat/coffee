import { FormEvent, useCallback, useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { ApiError } from '../api/client';
import {
  DiningSession,
  clearDiningDrafts,
  fetchDiningSession,
  placeDiningRound,
  removeDiningDraft,
  requestDiningBill,
  setDiningPaymentMethod,
  uploadDiningPaymentProof,
} from '../api/dining';
import { fetchMenuCatalogue } from '../api/catalog';
import { useLiveCanonicalSync } from '../notifications/useLiveCanonicalSync';

export function DiningSessionPage() {
  const { sessionId = '' } = useParams();
  const navigate = useNavigate();
  const [session, setSession] = useState<DiningSession | null>(null);
  const [variantId, setVariantId] = useState<number | null>(null);
  const [variants, setVariants] = useState<Array<{ id: number; label: string }>>([]);
  const [quantity, setQuantity] = useState(1);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [proofFile, setProofFile] = useState<File | null>(null);

  const reload = useCallback(async (): Promise<void> => {
    const response = await fetchDiningSession(sessionId);
    setSession(response.data);
  }, [sessionId]);

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
  useEffect(() => {
    let cancelled = false;

    void (async () => {
      try {
        const [sessionResponse, catalog] = await Promise.all([
          fetchDiningSession(sessionId),
          fetchMenuCatalogue(),
        ]);
        if (cancelled) {
          return;
        }

        setSession(sessionResponse.data);
        const options = catalog
          .flatMap((product) =>
            (product.variants ?? []).map((variant) => ({
              id: variant.id,
              label: `${product.name} — ${variant.name}`,
            })),
          )
          .slice(0, 40);
        setVariants(options);
        setVariantId(options[0]?.id ?? null);
      } catch (err) {
        if (!cancelled) {
          setError(err instanceof ApiError ? err.message : 'Unable to load dining session.');
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [sessionId]);

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

  async function onAddDraft(event: FormEvent): Promise<void> {
    event.preventDefault();
    if (!variantId) {
      return;
    }

    await run(async () => {
      const { addDiningDraft } = await import('../api/dining');
      await addDiningDraft(sessionId, { product_variant_id: variantId, quantity });
    });
  }

  if (!session) {
    return (
      <main className="page dining-session-page">
        <h1>Dining session</h1>
        {error ? <p className="form-error-text">{error}</p> : <p>Loading…</p>}
      </main>
    );
  }

  const canOrder = session.capabilities?.can_add_rounds ?? session.status === 'open';
  const billTotal = session.totals?.total ?? session.running_bill?.total ?? '0.00';

  return (
    <main className="page dining-session-page">
      <h1>{session.table.label}</h1>
      <p className="muted">
        {session.session_number} · {session.status_label ?? session.status}
      </p>
      <p>
        Running total: <strong>{billTotal}</strong>
      </p>

      {error ? (
        <p className="form-error-text" role="alert">
          {error}
        </p>
      ) : null}

      {canOrder ? (
        <section className="stack gap-3">
          <h2>Order more</h2>
          <form onSubmit={onAddDraft} className="stack gap-3">
            <label className="field">
              <span>Item</span>
              <select
                value={variantId ?? ''}
                onChange={(event) => setVariantId(Number(event.target.value) || null)}
              >
                {variants.map((variant) => (
                  <option key={variant.id} value={variant.id}>
                    {variant.label}
                  </option>
                ))}
              </select>
            </label>
            <label className="field">
              <span>Qty</span>
              <input
                type="number"
                min={1}
                value={quantity}
                onChange={(event) => setQuantity(Number(event.target.value) || 1)}
              />
            </label>
            <button className="btn btn-secondary" type="submit" disabled={busy}>
              Add to draft
            </button>
          </form>

          <ul className="stack gap-2">
            {session.drafts.map((draft) => (
              <li key={draft.id} className="row between">
                <span>
                  {draft.quantity} × {draft.product_name} {draft.variant_name ? `(${draft.variant_name})` : ''}
                </span>
                <button
                  type="button"
                  className="btn btn-text"
                  disabled={busy}
                  onClick={() => void run(() => removeDiningDraft(sessionId, draft.id).then(() => undefined))}
                >
                  Remove
                </button>
              </li>
            ))}
          </ul>

          <div className="row gap-2">
            <button
              type="button"
              className="btn btn-primary"
              disabled={busy || session.drafts.length === 0}
              onClick={() => void run(() => placeDiningRound(sessionId).then(() => undefined))}
            >
              Place round
            </button>
            <button
              type="button"
              className="btn btn-text"
              disabled={busy || session.drafts.length === 0}
              onClick={() => void run(() => clearDiningDrafts(sessionId).then(() => undefined))}
            >
              Clear draft
            </button>
          </div>
        </section>
      ) : null}

      <section className="stack gap-2">
        <h2>Rounds</h2>
        {session.rounds.length === 0 ? <p className="muted">No rounds yet.</p> : null}
        {session.rounds.map((round, index) => (
          <div key={String(round.id ?? index)}>
            Round {String(round.dining_round_number ?? index + 1)} · {String(round.order_number ?? '')} ·{' '}
            {String(round.status ?? '')}
          </div>
        ))}
      </section>

      {canOrder ? (
        <button
          type="button"
          className="btn btn-warning"
          disabled={busy || session.rounds.length === 0}
          onClick={() =>
            void run(async () => {
              await requestDiningBill(sessionId);
              navigate(`/dining/sessions/${sessionId}/bill`);
            })
          }
        >
          Finish & request bill
        </button>
      ) : (
        <Link className="btn btn-primary" to={`/dining/sessions/${sessionId}/bill`}>
          Open bill / payment
        </Link>
      )}
    </main>
  );
}

export function DiningBillPage() {
  const { sessionId = '' } = useParams();
  const [session, setSession] = useState<DiningSession | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [proofFile, setProofFile] = useState<File | null>(null);

  useEffect(() => {
    void fetchDiningSession(sessionId)
      .then((response) => setSession(response.data))
      .catch((err: unknown) => setError(err instanceof ApiError ? err.message : 'Unable to load bill.'));
  }, [sessionId]);

  if (!session) {
    return (
      <main className="page dining-bill-page">
        <h1>Bill</h1>
        {error ? <p className="form-error-text">{error}</p> : <p>Loading…</p>}
      </main>
    );
  }

  return (
    <main className="page dining-bill-page">
      <h1>Bill · {session.table.label}</h1>
      <p className="muted">{session.status_label ?? session.status}</p>
      <p>
        Total due: <strong>{session.totals.total}</strong>
      </p>
      {error ? (
        <p className="form-error-text" role="alert">
          {error}
        </p>
      ) : null}

      <div className="stack gap-3">
        <button
          type="button"
          className="btn btn-secondary"
          disabled={busy}
          onClick={() => {
            setBusy(true);
            void setDiningPaymentMethod(sessionId, 'cash')
              .then((response) => setSession(response.data))
              .catch((err: unknown) => setError(err instanceof ApiError ? err.message : 'Unable to set cash.'))
              .finally(() => setBusy(false));
          }}
        >
          Pay with cash (waiter will confirm)
        </button>

        <button
          type="button"
          className="btn btn-secondary"
          disabled={busy}
          onClick={() => {
            setBusy(true);
            void setDiningPaymentMethod(sessionId, 'manual_upi')
              .then((response) => setSession(response.data))
              .catch((err: unknown) => setError(err instanceof ApiError ? err.message : 'Unable to set UPI.'))
              .finally(() => setBusy(false));
          }}
        >
          Pay with UPI
        </button>

        {session.capabilities?.can_upload_payment_proof ? (
          <form
            className="stack gap-2"
            onSubmit={(event) => {
              event.preventDefault();
              if (!proofFile) {
                setError('Choose a payment screenshot.');
                return;
              }
              setBusy(true);
              void uploadDiningPaymentProof(sessionId, proofFile)
                .then((response) => setSession(response.data))
                .catch((err: unknown) =>
                  setError(err instanceof ApiError ? err.message : 'Unable to upload proof.'),
                )
                .finally(() => setBusy(false));
            }}
          >
            <label className="field">
              <span>UPI payment proof</span>
              <input
                type="file"
                accept="image/*,application/pdf"
                onChange={(event) => setProofFile(event.target.files?.[0] ?? null)}
              />
            </label>
            <button className="btn btn-primary" type="submit" disabled={busy}>
              Upload proof
            </button>
          </form>
        ) : null}
      </div>

      <Link to={`/dining/sessions/${sessionId}`} className="btn btn-text">
        Back to session
      </Link>
    </main>
  );
}
