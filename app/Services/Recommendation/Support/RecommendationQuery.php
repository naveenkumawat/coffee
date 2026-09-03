<?php

namespace App\Services\Recommendation\Support;

use App\Enums\RecommendationContext;
use App\Models\User;

final class RecommendationQuery
{
    /**
     * @param  list<int>  $cartProductIds
     * @param  list<int>  $excludeProductIds
     * @param  array<string, mixed>|null  $profile
     */
    public function __construct(
        public readonly RecommendationContext $context,
        public readonly ?User $customer = null,
        public readonly ?string $visitorKey = null,
        public readonly ?int $productId = null,
        public readonly ?int $categoryId = null,
        public readonly array $cartProductIds = [],
        public readonly array $excludeProductIds = [],
        public readonly int $limit = 8,
        public readonly ?array $profile = null,
        public readonly bool $hasSufficientEvidence = false,
    ) {}
}
