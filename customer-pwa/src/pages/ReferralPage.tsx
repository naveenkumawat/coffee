import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { fetchReferralSummary } from '../api/cart';
import { ApiError } from '../api/client';
import { ErrorState } from '../components/common/ErrorState';
import { LoadingSkeleton } from '../components/common/LoadingSkeleton';
import { FormFeedback } from '../components/forms/FormFeedback';
import { useToastStore } from '../stores/toastStore';

export function ReferralPage() {
  const toastSuccess = useToastStore((state) => state.success);
  const [isLoading, setIsLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [summary, setSummary] = useState<{
    enabled: boolean;
    referral_code: string;
    share_url: string;
    customer_message: string | null;
    stats: {
      successful_referrals: number;
      available_rewards: number;
      redeemed_rewards: number;
      expired_rewards: number;
    };
  } | null>(null);

  useEffect(() => {
    let cancelled = false;

    void (async () => {
      setIsLoading(true);
      setErrorMessage(null);

      try {
        const response = await fetchReferralSummary();
        if (!cancelled) {
          setSummary(response.data);
        }
      } catch (error) {
        if (!cancelled) {
          setErrorMessage(error instanceof ApiError ? error.message : 'Unable to load referral details.');
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

  async function copyShareUrl(): Promise<void> {
    if (!summary?.share_url) {
      return;
    }

    await navigator.clipboard.writeText(summary.share_url);
    toastSuccess('Referral link copied');
  }

  if (isLoading) {
    return (
      <div className="page-container">
        <LoadingSkeleton lines={4} />
      </div>
    );
  }

  if (errorMessage || !summary) {
    return (
      <div className="page-container">
        <ErrorState title="Referrals unavailable" description={errorMessage ?? 'Try again shortly.'} />
      </div>
    );
  }

  return (
    <div className="page-container account-page">
      <section className="account-hero account-hero-clean motion-enter">
        <p className="eyebrow">Referrals</p>
        <h2>Invite friends</h2>
        <p>{summary.customer_message ?? 'Share your code. When they place a qualifying order, you earn a reward.'}</p>
      </section>

      {!summary.enabled ? (
        <FormFeedback message="Referrals are paused right now." variant="error" />
      ) : (
        <section className="account-section">
          <div className="account-section-heading">
            <div>
              <span className="auth-badge">Your code</span>
              <h2>{summary.referral_code}</h2>
              <p>{summary.share_url}</p>
            </div>
          </div>
          <button type="button" className="btn btn-primary btn-lg rounded-pill w-100" onClick={() => void copyShareUrl()}>
            Copy invite link
          </button>
        </section>
      )}

      <section className="account-section">
        <div className="account-section-heading">
          <div>
            <span className="auth-badge">Stats</span>
            <h2>Your referrals</h2>
          </div>
        </div>
        <ul className="account-link-list is-quiet">
          <li className="account-link-row">Successful referrals · {summary.stats.successful_referrals}</li>
          <li className="account-link-row">Available rewards · {summary.stats.available_rewards}</li>
          <li className="account-link-row">Redeemed · {summary.stats.redeemed_rewards}</li>
          <li className="account-link-row">Expired · {summary.stats.expired_rewards}</li>
        </ul>
        <Link to="/account/rewards" className="btn btn-outline-dark rounded-pill w-100 mt-3">
          View rewards
        </Link>
      </section>
    </div>
  );
}
