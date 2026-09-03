<?php

namespace App\Services\Recommendation\Strategies;

use App\Enums\RecommendationReason;
use App\Services\Recommendation\Contracts\RecommendationStrategyInterface;
use App\Services\Recommendation\Support\RecommendationCandidate;
use App\Services\Recommendation\Support\RecommendationQuery;

class AffinityStrategy implements RecommendationStrategyInterface
{
    public function key(): string
    {
        return 'affinity';
    }

    public function candidates(RecommendationQuery $query): array
    {
        if ($query->profile === null || ! ($query->hasSufficientEvidence)) {
            return [];
        }

        $candidates = [];

        foreach ($query->profile['product_affinities'] ?? [] as $row) {
            $productId = (int) ($row['id'] ?? 0);
            $score = (float) ($row['score'] ?? 0);

            if ($productId <= 0 || $score <= 0) {
                continue;
            }

            $candidates[] = new RecommendationCandidate(
                productId: $productId,
                strategy: $this->key(),
                reason: RecommendationReason::BasedOnYourInterests,
                baseScore: $score,
                evidence: ['affinity' => 'product'],
            );
        }

        // Category affinity expands via similar strategy; here keep product-level only.

        return $candidates;
    }
}
