import { FormEvent, useEffect, useMemo, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { ApiError } from '../api/client';
import {
  DiningTableOption,
  fetchActiveDiningSession,
  fetchDiningTables,
  startDiningSession,
} from '../api/dining';
import { useContentStore } from '../stores/contentStore';

export function DiningPage() {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const preselect = searchParams.get('table')?.trim() ?? '';
  const content = useContentStore((state) => state.content);
  const diningEnabled = Boolean(
    content?.fulfilment?.dining_enabled ?? content?.fulfilment?.dine_in_enabled,
  );

  const [tables, setTables] = useState<DiningTableOption[]>([]);
  const [tableId, setTableId] = useState<number | null>(null);
  const [guestCount, setGuestCount] = useState(2);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    let cancelled = false;

    void (async () => {
      try {
        const active = await fetchActiveDiningSession();
        if (cancelled) {
          return;
        }

        if (active.data?.id) {
          navigate(`/dining/sessions/${active.data.id}`, { replace: true });
          return;
        }

        const response = await fetchDiningTables();
        if (cancelled) {
          return;
        }

        setTables(response.data);
        const matched = response.data.find(
          (table) =>
            table.code.toLowerCase() === preselect.toLowerCase() ||
            table.label.toLowerCase() === preselect.toLowerCase(),
        );
        setTableId(matched?.id ?? response.data.find((table) => table.available)?.id ?? null);
      } catch (err) {
        if (!cancelled) {
          setError(err instanceof ApiError ? err.message : 'Unable to load dining tables.');
        }
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [navigate, preselect]);

  const availableTables = useMemo(() => tables.filter((table) => table.available), [tables]);

  async function onSubmit(event: FormEvent): Promise<void> {
    event.preventDefault();
    if (!tableId) {
      setError('Choose a table to start dining.');
      return;
    }

    setSubmitting(true);
    setError(null);

    try {
      const response = await startDiningSession({
        cafe_table_id: tableId,
        guest_count: guestCount,
      });
      navigate(`/dining/sessions/${response.data.id}`);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Unable to start dining session.');
    } finally {
      setSubmitting(false);
    }
  }

  if (!diningEnabled) {
    return (
      <main className="page dining-page">
        <h1>Dining</h1>
        <p className="muted">Table service is currently unavailable. You can still order takeaway or delivery.</p>
        <Link to="/menu" className="btn btn-primary">
          Browse menu
        </Link>
      </main>
    );
  }

  return (
    <main className="page dining-page">
      <h1>Dining</h1>
      <p className="muted">Pick your table, order in rounds, then pay when you finish.</p>

      {loading ? <p>Loading tables…</p> : null}
      {error ? (
        <p className="form-error-text" role="alert">
          {error}
        </p>
      ) : null}

      {!loading ? (
        <form onSubmit={onSubmit} className="stack gap-4">
          <label className="field">
            <span>Table</span>
            <select
              value={tableId ?? ''}
              onChange={(event) => setTableId(event.target.value ? Number(event.target.value) : null)}
              required
            >
              <option value="" disabled>
                Select a table
              </option>
              {availableTables.map((table) => (
                <option key={table.id} value={table.id}>
                  {table.label}
                </option>
              ))}
            </select>
          </label>

          <label className="field">
            <span>Guests</span>
            <input
              type="number"
              min={1}
              max={50}
              value={guestCount}
              onChange={(event) => setGuestCount(Number(event.target.value) || 1)}
            />
          </label>

          <button className="btn btn-primary" type="submit" disabled={submitting || !tableId}>
            {submitting ? 'Starting…' : 'Start session'}
          </button>
        </form>
      ) : null}
    </main>
  );
}
