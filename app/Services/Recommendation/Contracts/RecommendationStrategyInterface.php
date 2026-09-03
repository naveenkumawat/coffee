<?php

namespace App\Services\Recommendation\Contracts;

use App\Services\Recommendation\Support\RecommendationCandidate;
use App\Services\Recommendation\Support\RecommendationQuery;

interface RecommendationStrategyInterface
{
    public function key(): string;

    /**
     * @return list<RecommendationCandidate>
     */
    public function candidates(RecommendationQuery $query): array;
}
