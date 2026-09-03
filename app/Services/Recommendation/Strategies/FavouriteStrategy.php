<?php

namespace App\Services\Recommendation\Strategies;

use App\Enums\RecommendationReason;
use App\Services\Favourite\FavouriteServiceInterface;
use App\Services\Recommendation\Contracts\RecommendationStrategyInterface;
use App\Services\Recommendation\Support\RecommendationCandidate;
use App\Services\Recommendation\Support\RecommendationQuery;

class FavouriteStrategy implements RecommendationStrategyInterface
{
    public function __construct(
        protected FavouriteServiceInterface $favourites,
    ) {}

    public function key(): string
    {
        return 'favourite';
    }

    public function candidates(RecommendationQuery $query): array
    {
        if ($query->customer === null) {
            return [];
        }

        $ids = $this->favourites->productIdsForCustomer($query->customer)->values()->all();
        $candidates = [];

        foreach ($ids as $index => $productId) {
            $candidates[] = new RecommendationCandidate(
                productId: (int) $productId,
                strategy: $this->key(),
                reason: RecommendationReason::Favourite,
                baseScore: 10.0 - min(5.0, $index * 0.1),
                evidence: ['source' => 'favourite'],
            );
        }

        return $candidates;
    }
}
