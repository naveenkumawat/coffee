<?php

namespace App\Support;

use App\Enums\CustomerRewardType;

/**
 * Customer-facing discount line items from stored snapshots / evaluation results.
 * Aggregate discount_total remains authoritative for money math; these lines explain it.
 */
final class CustomerDiscountLines
{
    /**
     * @param  iterable<mixed>  $promotions  OrderPromotion|DiningSessionPromotion snapshots
     * @return list<array{name: string, code: ?string, type: string, amount: string}>
     */
    public static function fromPromotionSnapshots(iterable $promotions): array
    {
        $lines = [];

        foreach ($promotions as $promotion) {
            $amount = self::money($promotion->discount_amount ?? '0');
            if (bccomp($amount, '0', 2) <= 0) {
                continue;
            }

            $name = trim((string) ($promotion->name_snapshot ?? ''));

            $lines[] = [
                'name' => $name !== '' ? $name : 'Discount',
                'code' => filled($promotion->code_snapshot ?? null) ? (string) $promotion->code_snapshot : null,
                'type' => 'promotion',
                'amount' => $amount,
            ];
        }

        return $lines;
    }

    /**
     * @param  list<array{promotion_id?: int, name: string, code: ?string, discount_type?: string, discount_value?: string, amount: string}>  $discounts
     * @return list<array{name: string, code: ?string, type: string, amount: string, promotion_id?: int, discount_type?: string, discount_value?: string}>
     */
    public static function fromPromotionEvaluation(array $discounts): array
    {
        $lines = [];

        foreach ($discounts as $discount) {
            $amount = self::money($discount['amount'] ?? '0');
            if (bccomp($amount, '0', 2) <= 0) {
                continue;
            }

            $name = trim((string) ($discount['name'] ?? ''));
            $line = [
                'name' => $name !== '' ? $name : 'Discount',
                'code' => filled($discount['code'] ?? null) ? (string) $discount['code'] : null,
                'type' => 'promotion',
                'amount' => $amount,
            ];

            if (isset($discount['promotion_id'])) {
                $line['promotion_id'] = (int) $discount['promotion_id'];
            }
            if (isset($discount['discount_type'])) {
                $line['discount_type'] = (string) $discount['discount_type'];
            }
            if (isset($discount['discount_value'])) {
                $line['discount_value'] = (string) $discount['discount_value'];
            }

            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * Referral coupon redemptions only — free-drink benefit is presented separately.
     *
     * @param  iterable<mixed>  $redemptions
     * @return list<array{name: string, code: ?string, type: string, amount: string}>
     */
    public static function fromReferralCouponRedemptions(iterable $redemptions): array
    {
        $lines = [];

        foreach ($redemptions as $redemption) {
            $type = $redemption->reward_type instanceof CustomerRewardType
                ? $redemption->reward_type
                : CustomerRewardType::tryFrom((string) $redemption->reward_type);

            if ($type !== CustomerRewardType::Coupon) {
                continue;
            }

            $amount = self::money($redemption->benefit_amount ?? '0');
            if (bccomp($amount, '0', 2) <= 0) {
                continue;
            }

            $name = trim((string) ($redemption->description_snapshot ?? ''));

            $lines[] = [
                'name' => $name !== '' ? $name : 'Referral Reward',
                'code' => filled($redemption->coupon_code_snapshot ?? null)
                    ? (string) $redemption->coupon_code_snapshot
                    : null,
                'type' => 'referral',
                'amount' => $amount,
            ];
        }

        return $lines;
    }

    /**
     * @param  list<array{reward_id?: int, reward_type: string, title: string, benefit_amount: string, code?: ?string}>  $referralRewards
     * @return list<array{name: string, code: ?string, type: string, amount: string}>
     */
    public static function fromCartReferralRewards(array $referralRewards): array
    {
        $lines = [];

        foreach ($referralRewards as $reward) {
            if (($reward['reward_type'] ?? null) !== CustomerRewardType::Coupon->value) {
                continue;
            }

            $amount = self::money($reward['benefit_amount'] ?? '0');
            if (bccomp($amount, '0', 2) <= 0) {
                continue;
            }

            $name = trim((string) ($reward['title'] ?? ''));

            $lines[] = [
                'name' => $name !== '' ? $name : 'Referral Reward',
                'code' => filled($reward['code'] ?? null) ? (string) $reward['code'] : null,
                'type' => 'referral',
                'amount' => $amount,
            ];
        }

        return $lines;
    }

    public static function money(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
