import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { fetchLoyalty, LoyaltyPayload } from '../api/loyalty';
import { ApiError } from '../api/client';
import { EmptyState } from '../components/common/EmptyState';
import { ErrorState } from '../components/common/ErrorState';
import { LoadingSkeleton } from '../components/common/LoadingSkeleton';

export function LoyaltyPage() {
  const [isLoading, setIsLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [loyalty, setLoyalty] = useState<LoyaltyPayload | null>(null);

  useEffect(() => {
    let cancelled = false;

    void (async () => {
      setIsLoading(true);
      setErrorMessage(null);

      try {
        const response = await fetchLoyalty();
        if (!cancelled) {
          setLoyalty(response.data);
        }
      } catch (error) {
        if (!cancelled) {
          setErrorMessage(error instanceof ApiError ? error.message : 'Unable to load loyalty points.');
        }
      } finally {
        if (!cancelled) {
          setIsLoading(false);
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, []);

  if (isLoading) {
    return (
      <div className="page-container">
        <LoadingSkeleton lines={3} />
      </div>
    );
  }

  if (errorMessage || !loyalty) {
    return (
      <div className="page-container">
        <ErrorState title="Loyalty unavailable" description={errorMessage ?? 'Unable to load loyalty points.'} />
      </div>
    );
  }

  return (
    <div className="page-container account-page">
      <section className="account-hero account-hero-clean motion-enter">
        <p className="eyebrow">Loyalty</p>
        <h2>{loyalty.available_points} points</h2>
        <p>
          Lifetime earned: {loyalty.lifetime_earned_points}
          {loyalty.lifetime_redeemed_points > 0 ? ` · Redeemed: ${loyalty.lifetime_redeemed_points}` : ''}
        </p>
      </section>

      {loyalty.earning_explanation ? (
        <section className="account-section motion-enter">
          <div className="account-section-heading">
            <div>
              <span className="auth-badge">How it works</span>
              <h2>Earning points</h2>
              <p>{loyalty.earning_explanation}</p>
            </div>
          </div>
          {!loyalty.earning_enabled ? (
            <p className="text-muted">Loyalty earning is currently paused.</p>
          ) : null}
        </section>
      ) : null}

      <section className="account-section motion-enter">
        <div className="account-section-heading">
          <div>
            <span className="auth-badge">Activity</span>
            <h2>Recent points</h2>
            <p>Redemption is coming soon.</p>
          </div>
        </div>

        {loyalty.recent_transactions.length === 0 ? (
          <EmptyState
            title="No points activity yet"
            description="Complete a paid order to start earning loyalty points."
            actionLabel="Browse menu"
            actionHref="/menu"
          />
        ) : (
          <div className="account-link-list">
            {loyalty.recent_transactions.map((txn) => (
              <div key={txn.id} className="account-link-row">
                <span>
                  <strong>{txn.label}</strong>
                  <br />
                  <span className="text-muted">
                    {txn.description ?? ''}
                    {txn.occurred_at ? ` · ${new Date(txn.occurred_at).toLocaleString()}` : ''}
                  </span>
                </span>
                <strong className={txn.points >= 0 ? 'text-success' : 'text-danger'}>
                  {txn.points > 0 ? `+${txn.points}` : txn.points}
                </strong>
              </div>
            ))}
          </div>
        )}
      </section>

      <p className="mt-4">
        <Link to="/account" className="text-link">
          Back to account
        </Link>
      </p>
    </div>
  );
}
