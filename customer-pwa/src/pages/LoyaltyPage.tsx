import { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { fetchLoyalty, LoyaltyPayload, LoyaltyRewardOption } from '../api/loyalty';
import { ApiError } from '../api/client';
import { EmptyState } from '../components/common/EmptyState';
import { ErrorState } from '../components/common/ErrorState';
import { LoadingSkeleton } from '../components/common/LoadingSkeleton';
import { LoyaltyProgress } from '../components/loyalty/LoyaltyProgress';
import { LoyaltyRewardCard } from '../components/loyalty/LoyaltyRewardCard';
import { trackBehaviour } from '../tracking/behaviourTracker';
import { formatDateTime } from '../utils/format';

function earnMoreRewards(rewards: LoyaltyRewardOption[]): LoyaltyRewardOption[] {
  return rewards.filter(
    (reward) => !reward.eligible && reward.points_needed > 0 && reward.state !== 'debt',
  );
}

function otherLockedRewards(rewards: LoyaltyRewardOption[]): LoyaltyRewardOption[] {
  return rewards.filter(
    (reward) => !reward.eligible && !(reward.points_needed > 0 && reward.state !== 'debt'),
  );
}

function formatTransactionMeta(txn: LoyaltyPayload['recent_transactions'][number]): string {
  const parts: string[] = [];

  if (txn.order_number) {
    parts.push(`Order ${txn.order_number}`);
  }

  if (txn.description) {
    parts.push(txn.description);
  }

  if (txn.occurred_at) {
    parts.push(formatDateTime(txn.occurred_at));
  }

  return parts.join(' · ');
}

export function LoyaltyPage() {
  const [isLoading, setIsLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [loyalty, setLoyalty] = useState<LoyaltyPayload | null>(null);
  const trackedView = useRef(false);

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

  useEffect(() => {
    if (!loyalty || trackedView.current) {
      return;
    }

    trackedView.current = true;

    const nearest = loyalty.next_reward;

    trackBehaviour({
      event_type: 'loyalty_reward_viewed',
      metadata: {
        reward_id: nearest?.reward_id ?? loyalty.available_now?.[0]?.id ?? null,
        points_cost: nearest?.points_cost ?? loyalty.available_now?.[0]?.points_cost ?? null,
        source: 'loyalty_hub',
      },
      dedupe_key: 'loyalty_reward_viewed:hub',
    });
  }, [loyalty]);

  if (isLoading) {
    return (
      <div className="page-container account-page">
        <LoadingSkeleton cardCount={2} lines={4} variant="hero" />
        <LoadingSkeleton cardCount={2} lines={3} />
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

  const availableNow = loyalty.available_now ?? loyalty.rewards?.filter((reward) => reward.eligible) ?? [];
  const locked = loyalty.locked ?? loyalty.rewards?.filter((reward) => !reward.eligible) ?? [];
  const earnMore = earnMoreRewards(locked);
  const lockedOther = otherLockedRewards(locked);
  const recentlyRedeemed = loyalty.recently_redeemed ?? [];
  const redemptionEnabled = loyalty.redemption_enabled !== false;
  const displayPoints = loyalty.display_available_points ?? Math.max(0, loyalty.available_points);

  return (
    <div className="page-container account-page">
      <section className="account-hero account-hero-clean motion-enter">
        <p className="eyebrow">Rewards</p>
        <h2>{displayPoints} points</h2>
        <p>
          Lifetime earned: {loyalty.lifetime_earned_points}
          {loyalty.lifetime_redeemed_points > 0 ? ` · Redeemed: ${loyalty.lifetime_redeemed_points}` : ''}
        </p>
        {loyalty.has_points_debt && (loyalty.debt_message || loyalty.debt_explanation) ? (
          <div className="loyalty-debt-banner">
            {loyalty.debt_message ? <strong>{loyalty.debt_message}</strong> : null}
            {loyalty.debt_explanation ? <p>{loyalty.debt_explanation}</p> : null}
          </div>
        ) : null}
      </section>

      <LoyaltyProgress nextReward={loyalty.next_reward} />

      {!redemptionEnabled ? (
        <section className="account-section motion-enter">
          <EmptyState
            title="Rewards paused"
            description="Loyalty rewards are not available right now. You can still earn points on eligible orders."
            actionLabel="Browse menu"
            actionHref="/menu"
          />
        </section>
      ) : null}

      {redemptionEnabled && loyalty.has_points_debt ? (
        <section className="account-section motion-enter">
          <EmptyState
            title="Redemption unavailable"
            description={
              loyalty.debt_explanation
              ?? 'Points adjustment pending. Future earned points will restore reward availability.'
            }
            actionLabel="Browse menu"
            actionHref="/menu"
          />
        </section>
      ) : null}

      {redemptionEnabled && !loyalty.has_points_debt && displayPoints === 0 && availableNow.length === 0 && earnMore.length === 0 ? (
        <section className="account-section motion-enter">
          <EmptyState
            title="Start earning points"
            description={
              loyalty.earning_explanation
              ?? 'Complete a paid order to start earning loyalty points toward rewards.'
            }
            actionLabel="Browse menu"
            actionHref="/menu"
          />
        </section>
      ) : null}

      {redemptionEnabled && !loyalty.has_points_debt && availableNow.length > 0 ? (
        <section className="account-section motion-enter">
          <div className="account-section-heading">
            <div>
              <span className="auth-badge">Redeem now</span>
              <h2>Available now</h2>
              <p>Apply a reward from your cart at checkout.</p>
            </div>
          </div>
          <div className="loyalty-reward-grid">
            {availableNow.map((reward) => (
              <LoyaltyRewardCard key={reward.id} reward={reward} />
            ))}
          </div>
          <p className="mt-3">
            <Link to="/cart" className="btn btn-primary rounded-pill">
              Redeem in cart
            </Link>
          </p>
        </section>
      ) : null}

      {redemptionEnabled && !loyalty.has_points_debt && earnMore.length > 0 ? (
        <section className="account-section motion-enter">
          <div className="account-section-heading">
            <div>
              <span className="auth-badge">Keep earning</span>
              <h2>Earn more</h2>
              <p>These rewards unlock as you collect more points.</p>
            </div>
          </div>
          <div className="loyalty-reward-grid">
            {earnMore.map((reward) => (
              <LoyaltyRewardCard key={reward.id} reward={reward} />
            ))}
          </div>
        </section>
      ) : null}

      {redemptionEnabled && lockedOther.length > 0 ? (
        <section className="account-section motion-enter">
          <div className="account-section-heading">
            <div>
              <span className="auth-badge">Coming soon</span>
              <h2>Locked</h2>
              <p>These rewards are not available yet.</p>
            </div>
          </div>
          <div className="loyalty-reward-grid">
            {lockedOther.map((reward) => (
              <LoyaltyRewardCard key={reward.id} reward={reward} />
            ))}
          </div>
        </section>
      ) : null}

      {redemptionEnabled && !loyalty.has_points_debt && availableNow.length === 0 && earnMore.length === 0 && lockedOther.length === 0 ? (
        <section className="account-section motion-enter">
          <EmptyState
            title="No rewards yet"
            description="Rewards will appear here when they become available for your account."
            actionLabel="Browse menu"
            actionHref="/menu"
          />
        </section>
      ) : null}

      {recentlyRedeemed.length > 0 ? (
        <section className="account-section motion-enter">
          <div className="account-section-heading">
            <div>
              <span className="auth-badge">Recent</span>
              <h2>Recently redeemed</h2>
            </div>
          </div>
          <div className="account-link-list">
            {recentlyRedeemed.map((item) => (
              <div key={item.transaction_id} className="account-link-row">
                <span>
                  <strong>{item.name}</strong>
                  <br />
                  <span className="text-muted">
                    {item.order_number ? `Order ${item.order_number}` : ''}
                    {item.order_number && item.occurred_at ? ' · ' : ''}
                    {item.occurred_at ? formatDateTime(item.occurred_at) : ''}
                  </span>
                </span>
                <strong className="text-danger">−{item.points} pts</strong>
              </div>
            ))}
          </div>
        </section>
      ) : null}

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
            <h2>Recent activity</h2>
          </div>
        </div>

        {loyalty.recent_transactions.length === 0 ? (
          <EmptyState
            title="No activity yet"
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
                  <span className="text-muted">{formatTransactionMeta(txn)}</span>
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
