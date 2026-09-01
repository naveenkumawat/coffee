import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { fetchActiveRewards } from '../api/cart';
import { ApiError } from '../api/client';
import { EmptyState } from '../components/common/EmptyState';
import { ErrorState } from '../components/common/ErrorState';
import { LoadingSkeleton } from '../components/common/LoadingSkeleton';

interface RewardRow {
  id: number;
  reward_type: string;
  title: string;
  coupon_code: string | null;
  expires_at: string | null;
}

export function RewardsPage() {
  const [isLoading, setIsLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [rewards, setRewards] = useState<RewardRow[]>([]);

  useEffect(() => {
    let cancelled = false;

    void (async () => {
      setIsLoading(true);
      setErrorMessage(null);

      try {
        const response = await fetchActiveRewards();
        if (!cancelled) {
          setRewards(response.data.rewards);
        }
      } catch (error) {
        if (!cancelled) {
          setErrorMessage(error instanceof ApiError ? error.message : 'Unable to load rewards.');
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

  if (errorMessage) {
    return (
      <div className="page-container">
        <ErrorState title="Rewards unavailable" description={errorMessage} />
      </div>
    );
  }

  return (
    <div className="page-container account-page">
      <section className="account-hero account-hero-clean motion-enter">
        <p className="eyebrow">Rewards</p>
        <h2>Ready to use</h2>
        <p>Apply free drinks and referral coupons from your cart at checkout.</p>
      </section>

      {rewards.length === 0 ? (
        <EmptyState
          title="No active rewards"
          description="Share your referral link to earn free drinks or coupons."
          actionLabel="Refer a friend"
          actionHref="/account/referral"
        />
      ) : (
        <section className="account-section">
          <ul className="account-link-list">
            {rewards.map((reward) => (
              <li key={reward.id} className="account-link-row">
                <span>
                  <strong>{reward.title}</strong>
                  {reward.coupon_code ? <small> · {reward.coupon_code}</small> : null}
                  {reward.expires_at ? (
                    <small> · expires {new Date(reward.expires_at).toLocaleDateString()}</small>
                  ) : null}
                </span>
              </li>
            ))}
          </ul>
          <Link to="/cart" className="btn btn-primary rounded-pill w-100 mt-3">
            Go to cart
          </Link>
        </section>
      )}
    </div>
  );
}
