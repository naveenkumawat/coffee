<?php

namespace App\Services\Recommendation\Strategies;

use App\Enums\RecommendationReason;
use App\Services\Recommendation\Contracts\RecommendationStrategyInterface;
use App\Services\Recommendation\RecommendationAggregateStore;
use App\Services\Recommendation\Support\RecommendationCandidate;
use App\Services\Recommendation\Support\RecommendationQuery;

class FrequentlyBoughtTogetherStrategy implements RecommendationStrategyInterface
{
    public function __construct(
        protected RecommendationAggregateStore $aggregates,
    ) {}

    public function key(): string
    {
        return 'frequently_bought_together';
    }

    public function candidates(RecommendationQuery $query): array
    {
        $seedIds = $query->cartProductIds;

        if ($query->productId) {
            $seedIds[] = $query->productId;
        }

        $seedIds = array_values(array_unique(array_filter(array_map('intval', $seedIds))));

        if ($seedIds === []) {
            return [];
        }

        $map = $this->aggregates->frequentlyBoughtTogetherMap();
        $merged = [];

        foreach ($seedIds as $seedId) {
            foreach ($map[$seedId] ?? [] as $related) {
                $productId = (int) $related['product_id'];

                if (in_array($productId, $seedIds, true)) {
                    continue;
                }

                $score = (float) $related['order_count'];
                $merged[$productId] = max($merged[$productId] ?? 0.0, $score);
            }
        }

        arsort($merged);
        $candidates = [];

        foreach ($merged as $productId => $score) {
            $candidates[] = new RecommendationCandidate(
                productId: (int) $productId,
                strategy: $this->key(),
                reason: RecommendationReason::FrequentlyBoughtTogether,
                baseScore: $score,
                evidence: ['co_occurrence_orders' => (int) $score],
            );
        }

        return array_slice($candidates, 0, 15);
    }
}
