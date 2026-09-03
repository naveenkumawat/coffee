<?php

namespace App\Services\Recommendation\Strategies;

use App\Enums\RecommendationReason;
use App\Services\Recommendation\Contracts\RecommendationStrategyInterface;
use App\Services\Recommendation\RecommendationAggregateStore;
use App\Services\Recommendation\Support\RecommendationCandidate;
use App\Services\Recommendation\Support\RecommendationQuery;

class TrendingStrategy implements RecommendationStrategyInterface
{
    public function __construct(
        protected RecommendationAggregateStore $aggregates,
    ) {}

    public function key(): string
    {
        return 'trending';
    }

    public function candidates(RecommendationQuery $query): array
    {
        $candidates = [];

        foreach ($this->aggregates->trendingProducts() as $row) {
            $candidates[] = new RecommendationCandidate(
                productId: (int) $row['product_id'],
                strategy: $this->key(),
                reason: RecommendationReason::Trending,
                baseScore: (float) $row['score'],
                evidence: ['actors' => (int) $row['actors']],
            );
        }

        return $candidates;
    }
}
