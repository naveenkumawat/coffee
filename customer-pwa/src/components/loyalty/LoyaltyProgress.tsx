import { LoyaltyNextReward } from '../../api/loyalty';

interface LoyaltyProgressProps {
  nextReward: LoyaltyNextReward | null | undefined;
}

export function LoyaltyProgress({ nextReward }: LoyaltyProgressProps) {
  if (!nextReward) {
    return null;
  }

  const {
    state,
    message,
    points_have: pointsHave,
    points_cost: pointsCost,
    points_needed: pointsNeeded,
    progress_percent: progressPercent,
    name,
  } = nextReward;

  const isIndeterminate = state === 'debt' || state === 'none' || state === 'disabled' || state === 'locked';
  const ariaMax = pointsCost && pointsCost > 0 ? pointsCost : 100;
  const ariaNow = isIndeterminate ? 0 : Math.min(pointsHave, ariaMax);
  const barWidth = isIndeterminate ? 0 : Math.max(0, Math.min(100, progressPercent));

  const pointsDisplay = (() => {
    if (state === 'ready') {
      return `${pointsHave} pts · ready to redeem`;
    }

    if (state === 'progress' && pointsNeeded !== null && name) {
      return `${pointsHave} / ${pointsCost} pts`;
    }

    if (pointsCost !== null && state === 'progress') {
      return `${pointsHave} / ${pointsCost} pts`;
    }

    return `${pointsHave} pts`;
  })();

  return (
    <section className="loyalty-progress account-section motion-enter" aria-labelledby="loyalty-progress-heading">
      <div className="account-section-heading">
        <div>
          <span className="auth-badge">Next reward</span>
          <h2 id="loyalty-progress-heading">{name ?? 'Your progress'}</h2>
          <p>{message}</p>
        </div>
      </div>

      <div
        className={`loyalty-progress-bar ${isIndeterminate ? 'is-indeterminate' : ''}`.trim()}
        role="progressbar"
        aria-valuemin={0}
        aria-valuemax={ariaMax}
        aria-valuenow={ariaNow}
        aria-valuetext={message}
        aria-label={name ? `Progress toward ${name}` : 'Loyalty reward progress'}
      >
        <div className="loyalty-progress-bar-fill" style={{ width: `${barWidth}%` }} />
      </div>

      <p className="loyalty-progress-points">{pointsDisplay}</p>
    </section>
  );
}
