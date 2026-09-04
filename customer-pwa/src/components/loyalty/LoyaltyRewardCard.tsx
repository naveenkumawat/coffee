import { LoyaltyRewardOption, LoyaltyRewardState } from '../../api/loyalty';

interface LoyaltyRewardCardProps {
  reward: LoyaltyRewardOption;
  onSelect?: (rewardId: number) => void;
  selected?: boolean;
  compact?: boolean;
}

const STATE_LABELS: Record<LoyaltyRewardState, string> = {
  available: 'Available',
  locked: 'Locked',
  limit_reached: 'Limit reached',
  scheduled: 'Scheduled',
  debt: 'Unavailable',
};

function stateBadgeClass(state: LoyaltyRewardState): string {
  if (state === 'available') {
    return 'is-available';
  }

  if (state === 'scheduled') {
    return 'is-scheduled';
  }

  if (state === 'limit_reached') {
    return 'is-limit';
  }

  return 'is-locked';
}

export function LoyaltyRewardCard({ reward, onSelect, selected = false, compact = false }: LoyaltyRewardCardProps) {
  const isSelectable = Boolean(onSelect) && reward.eligible;
  const stateLabel = STATE_LABELS[reward.state] ?? 'Locked';

  const body = (
    <>
      {reward.image_url && !compact ? (
        <img className="loyalty-reward-card-image" src={reward.image_url} alt="" loading="lazy" />
      ) : null}
      <div className="loyalty-reward-card-body">
        <div className="loyalty-reward-card-header">
          <h3 className="loyalty-reward-card-title">{reward.name}</h3>
          <span className={`loyalty-reward-card-badge ${stateBadgeClass(reward.state)}`}>{stateLabel}</span>
        </div>
        {!compact && reward.description ? (
          <p className="loyalty-reward-card-description">{reward.description}</p>
        ) : null}
        <p className="loyalty-reward-card-benefit">{reward.benefit_label}</p>
        <div className="loyalty-reward-card-meta">
          <span>{reward.points_cost} pts</span>
          {reward.minimum_spend ? <span>Min {reward.minimum_spend}</span> : null}
        </div>
        {reward.unavailable_message ? (
          <p className="loyalty-reward-card-unavailable" role="note">
            {reward.unavailable_message}
          </p>
        ) : null}
      </div>
    </>
  );

  if (isSelectable) {
    return (
      <button
        type="button"
        className={`loyalty-reward-card ${compact ? 'is-compact' : ''} ${selected ? 'is-selected' : ''}`.trim()}
        onClick={() => onSelect?.(reward.id)}
        aria-pressed={selected}
        aria-label={`Redeem ${reward.name} for ${reward.points_cost} points`}
      >
        {body}
      </button>
    );
  }

  return (
    <article
      className={`loyalty-reward-card is-static ${compact ? 'is-compact' : ''} ${!reward.eligible ? 'is-disabled' : ''}`.trim()}
      aria-disabled={!reward.eligible}
    >
      {body}
    </article>
  );
}
