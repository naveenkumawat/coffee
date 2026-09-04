<?php

namespace App\Services\Recommendation\Strategies;

use App\Enums\RecommendationReason;
use App\Services\Loyalty\LoyaltyPersonalisationContextServiceInterface;
use App\Services\Recommendation\Contracts\RecommendationStrategyInterface;
use App\Services\Recommendation\Support\RecommendationCandidate;
use App\Services\Recommendation\Support\RecommendationQuery;

/**
 * Explicit-only loyalty product rail. Never included in default warm/cold strategies.
 * Does not weight or distort general product ranking.
 */
class LoyaltyRewardEligibleStrategy implements RecommendationStrategyInterface
{
    public function __construct(
        protected LoyaltyPersonalisationContextServiceInterface $loyaltyContext,
    ) {}

    public function key(): string
    {
        return 'loyalty_reward_eligible';
    }

    public function candidates(RecommendationQuery $query): array
    {
        if ($query->customer === null) {
            return [];
        }

        $loyalty = $this->loyaltyContext->forActor($query->customer);
        $productIds = array_values(array_map('intval', $loyalty['eligible_product_ids'] ?? []));

        if ($productIds === []) {
            return [];
        }

        $candidates = [];

        foreach ($productIds as $index => $productId) {
            $candidates[] = new RecommendationCandidate(
                productId: $productId,
                strategy: $this->key(),
                reason: RecommendationReason::UseLoyaltyReward,
                baseScore: 9.0 - min(4.0, $index * 0.1),
                evidence: ['source' => 'loyalty_reward_eligible'],
            );
        }

        return $candidates;
    }
}
