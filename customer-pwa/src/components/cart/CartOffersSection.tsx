import { FormEvent, useEffect, useState } from 'react';
import { ApiError } from '../../api/client';
import { fetchActiveRewards } from '../../api/cart';
import { CartSummary } from '../../types/cart';
import { CheckoutFulfilmentMethod } from '../../types/checkout';
import { formatCurrency } from '../../utils/format';
import { cartDiscounts, hasDiscountSavings } from '../../utils/discounts';
import { getFieldError } from '../../utils/forms';
import { FormFeedback } from '../forms/FormFeedback';

interface ActiveReward {
  id: number;
  reward_type: string;
  title: string;
  coupon_code: string | null;
}

interface CartOffersSectionProps {
  summary: CartSummary | null;
  fulfilmentMethod?: CheckoutFulfilmentMethod | null;
  onApply: (promoCode: string, fulfilmentMethod?: CheckoutFulfilmentMethod | null) => Promise<void>;
  onRemove: () => Promise<void>;
  onApplyFreeDrink?: (rewardId: number) => Promise<void>;
  onApplyReferralCoupon?: (code: string) => Promise<void>;
  onClearReferralRewards?: () => Promise<void>;
  onApplyLoyaltyReward?: (rewardId: number) => Promise<void>;
  onClearLoyaltyReward?: () => Promise<void>;
}

export function CartOffersSection({
  summary,
  fulfilmentMethod = null,
  onApply,
  onRemove,
  onApplyFreeDrink,
  onApplyReferralCoupon,
  onClearReferralRewards,
  onApplyLoyaltyReward,
  onClearLoyaltyReward,
}: CartOffersSectionProps) {
  const discounts = cartDiscounts(summary);
  const appliedPromoCode = summary?.promo_code?.trim() || null;
  const referralRewards = summary?.referral_rewards ?? [];
  const loyaltyReward = summary?.loyalty_reward ?? null;
  const loyaltyRewards = summary?.loyalty_rewards ?? [];
  const eligibleLoyaltyRewards = loyaltyRewards.filter((reward) => reward.eligible);
  const [promoCode, setPromoCode] = useState(appliedPromoCode ?? '');
  const [errorMessage, setErrorMessage] = useState<string | null>(
    summary?.promo_error ?? summary?.reward_error ?? summary?.loyalty_error ?? null,
  );
  const [isApplying, setIsApplying] = useState(false);
  const [isRemoving, setIsRemoving] = useState(false);
  const [activeRewards, setActiveRewards] = useState<ActiveReward[]>([]);

  useEffect(() => {
    setPromoCode(appliedPromoCode ?? '');
    setErrorMessage(summary?.promo_error ?? summary?.reward_error ?? summary?.loyalty_error ?? null);
  }, [appliedPromoCode, summary?.promo_error, summary?.reward_error, summary?.loyalty_error]);

  useEffect(() => {
    let cancelled = false;

    void fetchActiveRewards()
      .then((response) => {
        if (!cancelled) {
          setActiveRewards(response.data.rewards);
        }
      })
      .catch(() => {
        if (!cancelled) {
          setActiveRewards([]);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [summary?.referral_rewards]);

  async function handleApply(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault();

    const code = promoCode.trim();

    if (!code || isApplying || isRemoving) {
      return;
    }

    setIsApplying(true);
    setErrorMessage(null);

    try {
      if (code.toUpperCase().startsWith('REF-') && onApplyReferralCoupon) {
        await onApplyReferralCoupon(code);
      } else {
        await onApply(code, fulfilmentMethod);
      }
    } catch (error) {
      if (error instanceof ApiError) {
        setErrorMessage(
          getFieldError(error.errors, 'promo_code')
            ?? getFieldError(error.errors, 'referral_coupon')
            ?? error.message,
        );
      } else {
        setErrorMessage('Unable to apply that code right now.');
      }
    } finally {
      setIsApplying(false);
    }
  }

  async function handleRemove(): Promise<void> {
    if (isApplying || isRemoving) {
      return;
    }

    setIsRemoving(true);
    setErrorMessage(null);

    try {
      await onRemove();
      setPromoCode('');
    } catch (error) {
      setErrorMessage(
        error instanceof ApiError ? error.message : 'Unable to remove this promo code right now.',
      );
    } finally {
      setIsRemoving(false);
    }
  }

  const freeDrinkBenefit = Number(summary?.free_drink_benefit ?? 0);
  const loyaltyDiscount = Number(summary?.loyalty_discount ?? 0);

  return (
    <section className="cart-offers" aria-labelledby="cart-offers-heading">
      <div className="cart-offers-heading">
        <h2 id="cart-offers-heading">Offers & rewards</h2>
        {hasDiscountSavings(summary?.discount_total) || freeDrinkBenefit > 0 || loyaltyDiscount > 0 ? (
          <p>
            Savings applied
            {freeDrinkBenefit > 0 ? ` · free drink ${formatCurrency(summary?.free_drink_benefit)}` : ''}
            {loyaltyDiscount > 0 ? ` · loyalty ${formatCurrency(summary?.loyalty_discount)}` : ''}
          </p>
        ) : (
          <p>Apply a coupon or referral reward</p>
        )}
      </div>

      {discounts.length > 0 ? (
        <ul className="cart-offer-list">
          {discounts.map((discount) => (
            <li key={`${discount.promotion_id ?? discount.name}-${discount.code ?? 'auto'}`}>
              <span>✓ {discount.name} applied</span>
              <strong>−{formatCurrency(discount.amount)}</strong>
            </li>
          ))}
        </ul>
      ) : null}

      {referralRewards.length > 0 ? (
        <ul className="cart-offer-list">
          {referralRewards.map((reward) => (
            <li key={reward.reward_id}>
              <span>✓ {reward.title}</span>
              <strong>−{formatCurrency(reward.benefit_amount)}</strong>
            </li>
          ))}
        </ul>
      ) : null}

      {referralRewards.length > 0 && onClearReferralRewards ? (
        <div className="cart-promo-applied">
          <div>
            <small>Referral reward on this cart</small>
          </div>
          <button
            type="button"
            className="link-button"
            onClick={() => void onClearReferralRewards()}
            disabled={isRemoving || isApplying}
          >
            Remove reward
          </button>
        </div>
      ) : null}

      {onApplyFreeDrink && activeRewards.some((reward) => reward.reward_type === 'free_drink') ? (
        <div className="cart-offer-list">
          {activeRewards
            .filter((reward) => reward.reward_type === 'free_drink')
            .map((reward) => (
              <button
                key={reward.id}
                type="button"
                className="btn btn-outline-dark rounded-pill btn-sm"
                disabled={isApplying}
                onClick={() => {
                  void (async () => {
                    setIsApplying(true);
                    setErrorMessage(null);
                    try {
                      await onApplyFreeDrink(reward.id);
                    } catch (error) {
                      setErrorMessage(
                        error instanceof ApiError
                          ? getFieldError(error.errors, 'reward_id') ?? error.message
                          : 'Unable to apply free drink reward.',
                      );
                    } finally {
                      setIsApplying(false);
                    }
                  })();
                }}
              >
                Use {reward.title}
              </button>
            ))}
        </div>
      ) : null}

      {loyaltyReward ? (
        <div className="cart-promo-applied">
          <div>
            <span className="cart-promo-code">{loyaltyReward.name}</span>
            <small>Loyalty reward · {loyaltyReward.points_cost} pts · −{formatCurrency(loyaltyReward.discount_amount)}</small>
          </div>
          {onClearLoyaltyReward ? (
            <button
              type="button"
              className="link-button"
              onClick={() => void onClearLoyaltyReward()}
              disabled={isRemoving || isApplying}
            >
              Remove
            </button>
          ) : null}
        </div>
      ) : onApplyLoyaltyReward && eligibleLoyaltyRewards.length > 0 ? (
        <div className="cart-offer-list">
          <label className="form-label" htmlFor="cart-loyalty-reward">Use loyalty reward</label>
          <select
            id="cart-loyalty-reward"
            className="form-select form-select-sm mb-2"
            defaultValue=""
            disabled={isApplying}
            onChange={(event) => {
              const rewardId = Number(event.target.value);
              if (!rewardId) {
                return;
              }

              void (async () => {
                setIsApplying(true);
                setErrorMessage(null);
                try {
                  await onApplyLoyaltyReward(rewardId);
                } catch (error) {
                  setErrorMessage(
                    error instanceof ApiError
                      ? getFieldError(error.errors, 'loyalty_reward_id') ?? error.message
                      : 'Unable to apply loyalty reward.',
                  );
                } finally {
                  setIsApplying(false);
                  event.target.value = '';
                }
              })();
            }}
          >
            <option value="">Select a reward…</option>
            {eligibleLoyaltyRewards.map((reward) => (
              <option key={reward.id} value={reward.id}>
                {reward.name} ({reward.points_cost} pts · save {formatCurrency(reward.preview_discount_amount)})
              </option>
            ))}
          </select>
        </div>
      ) : null}

      {appliedPromoCode ? (
        <div className="cart-promo-applied">
          <div>
            <span className="cart-promo-code">{appliedPromoCode}</span>
            <small>Promo code applied</small>
          </div>
          <button
            type="button"
            className="link-button"
            onClick={() => void handleRemove()}
            disabled={isRemoving || isApplying}
          >
            {isRemoving ? 'Removing…' : 'Remove'}
          </button>
        </div>
      ) : (
        <form className="cart-promo-form" onSubmit={(event) => void handleApply(event)}>
          <label className="form-field cart-promo-field" htmlFor="cart-promo-code">
            <span className="form-label visually-hidden">Promo or referral code</span>
            <input
              id="cart-promo-code"
              name="promo_code"
              className={`form-control form-control-lg coffee-input ${errorMessage ? 'is-invalid' : ''}`}
              value={promoCode}
              onChange={(event) => setPromoCode(event.target.value.toUpperCase())}
              placeholder="Promo or REF- code"
              autoComplete="off"
              spellCheck={false}
              disabled={isApplying}
              aria-invalid={errorMessage ? true : undefined}
            />
          </label>
          <button
            type="submit"
            className="btn btn-outline-dark rounded-pill"
            disabled={isApplying || promoCode.trim().length === 0}
          >
            {isApplying ? 'Applying…' : 'Apply'}
          </button>
        </form>
      )}

      <FormFeedback message={errorMessage} variant="error" />
    </section>
  );
}
