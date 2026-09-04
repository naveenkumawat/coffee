import { OrderLoyaltyFeedback } from '../../types/order';

interface LoyaltyFeedbackBannerProps {
  feedback: OrderLoyaltyFeedback | null | undefined;
}

export function LoyaltyFeedbackBanner({ feedback }: LoyaltyFeedbackBannerProps) {
  if (!feedback) {
    return null;
  }

  const messages: string[] = [];

  if (feedback.points_earned !== null && feedback.points_earned !== undefined) {
    messages.push(`+${feedback.points_earned} points earned`);
  } else if (feedback.earning_pending) {
    messages.push('Points will appear when confirmed');
  }

  if (feedback.points_redeemed !== null && feedback.points_redeemed !== undefined) {
    const rewardSuffix = feedback.reward_name ? ` · ${feedback.reward_name}` : '';
    messages.push(`Reward used: ${feedback.points_redeemed} points${rewardSuffix}`);
  }

  if (messages.length === 0) {
    return null;
  }

  return (
    <section className="account-section motion-enter" aria-label="Loyalty update">
      <div className="account-link-list">
        {messages.map((message) => (
          <div key={message} className="account-link-row">
            <span>
              <i className="bi bi-star" aria-hidden="true"></i>
              {message}
            </span>
          </div>
        ))}
      </div>
    </section>
  );
}
