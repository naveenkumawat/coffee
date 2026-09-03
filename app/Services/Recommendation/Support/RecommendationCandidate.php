<?php

namespace App\Services\Recommendation\Support;

use App\Enums\RecommendationReason;

final class RecommendationCandidate
{
    public function __construct(
        public readonly int $productId,
        public readonly string $strategy,
        public readonly RecommendationReason $reason,
        public readonly float $baseScore,
        public readonly array $evidence = [],
    ) {}
}
