<?php

namespace App\Services\Recommendation\Strategies;

use App\Enums\RecommendationReason;
use App\Services\Recommendation\Contracts\RecommendationStrategyInterface;
use App\Services\Recommendation\RecommendationAggregateStore;
use App\Services\Recommendation\Support\RecommendationCandidate;
use App\Services\Recommendation\Support\RecommendationQuery;

class NewArrivalStrategy implements RecommendationStrategyInterface
{
    public function __construct(
        protected RecommendationAggregateStore $aggregates,
    ) {}

    public function key(): string
    {
        return 'new_arrival';
    }

    public function candidates(RecommendationQuery $query): array
    {
        $candidates = [];

        foreach ($this->aggregates->newArrivalProductIds() as $index => $productId) {
            $candidates[] = new RecommendationCandidate(
                productId: (int) $productId,
                strategy: $this->key(),
                reason: RecommendationReason::NewArrival,
                baseScore: 5.0 - ($index * 0.05),
                evidence: ['source' => 'new_arrival_window'],
            );
        }

        return $candidates;
    }
}
