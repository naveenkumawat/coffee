<?php

namespace App\Services\Recommendation\Strategies;

use App\Enums\RecommendationReason;
use App\Services\Recommendation\Contracts\RecommendationStrategyInterface;
use App\Services\Recommendation\RecommendationAggregateStore;
use App\Services\Recommendation\Support\RecommendationCandidate;
use App\Services\Recommendation\Support\RecommendationQuery;

class BestsellerStrategy implements RecommendationStrategyInterface
{
    public function __construct(
        protected RecommendationAggregateStore $aggregates,
    ) {}

    public function key(): string
    {
        return 'bestseller';
    }

    public function candidates(RecommendationQuery $query): array
    {
        $candidates = [];

        foreach ($this->aggregates->bestsellerTaggedProductIds() as $index => $productId) {
            $candidates[] = new RecommendationCandidate(
                productId: (int) $productId,
                strategy: $this->key(),
                reason: RecommendationReason::Bestseller,
                baseScore: 3.5 - ($index * 0.05),
                evidence: ['source' => 'merchandising_tag'],
            );
        }

        return $candidates;
    }
}
