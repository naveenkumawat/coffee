<?php

namespace App\Services\Recommendation\Strategies;

use App\Enums\RecommendationReason;
use App\Services\Recommendation\Contracts\RecommendationStrategyInterface;
use App\Services\Recommendation\RecommendationAggregateStore;
use App\Services\Recommendation\Support\RecommendationCandidate;
use App\Services\Recommendation\Support\RecommendationQuery;

class PopularStrategy implements RecommendationStrategyInterface
{
    public function __construct(
        protected RecommendationAggregateStore $aggregates,
    ) {}

    public function key(): string
    {
        return 'popular';
    }

    public function candidates(RecommendationQuery $query): array
    {
        $candidates = [];

        foreach ($this->aggregates->popularProducts() as $row) {
            $candidates[] = new RecommendationCandidate(
                productId: (int) $row['product_id'],
                strategy: $this->key(),
                reason: RecommendationReason::Popular,
                baseScore: ((float) $row['order_count'] * 2.0) + ((float) $row['quantity'] * 0.25),
                evidence: [
                    'order_count' => (int) $row['order_count'],
                    'quantity' => (int) $row['quantity'],
                ],
            );
        }

        return $candidates;
    }
}
