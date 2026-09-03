<?php

namespace App\Services\Recommendation\Strategies;

use App\Enums\RecommendationReason;
use App\Services\Recommendation\Contracts\RecommendationStrategyInterface;
use App\Services\Recommendation\RecommendationAggregateStore;
use App\Services\Recommendation\Support\RecommendationCandidate;
use App\Services\Recommendation\Support\RecommendationQuery;

class FeaturedStrategy implements RecommendationStrategyInterface
{
    public function __construct(
        protected RecommendationAggregateStore $aggregates,
    ) {}

    public function key(): string
    {
        return 'featured';
    }

    public function candidates(RecommendationQuery $query): array
    {
        $candidates = [];

        foreach ($this->aggregates->featuredProductIds() as $index => $productId) {
            $candidates[] = new RecommendationCandidate(
                productId: (int) $productId,
                strategy: $this->key(),
                reason: RecommendationReason::Featured,
                baseScore: 4.0 - ($index * 0.05),
                evidence: ['source' => 'manual_featured'],
            );
        }

        return $candidates;
    }
}
