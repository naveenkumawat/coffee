<?php

namespace App\Services\Recommendation\Strategies;

use App\Enums\RecommendationReason;
use App\Models\Product;
use App\Services\Recommendation\Contracts\RecommendationStrategyInterface;
use App\Services\Recommendation\RecommendationAggregateStore;
use App\Services\Recommendation\Support\RecommendationCandidate;
use App\Services\Recommendation\Support\RecommendationQuery;

class CartContextStrategy implements RecommendationStrategyInterface
{
    public function __construct(
        protected RecommendationAggregateStore $aggregates,
    ) {}

    public function key(): string
    {
        return 'cart_context';
    }

    public function candidates(RecommendationQuery $query): array
    {
        if ($query->cartProductIds === []) {
            return [];
        }

        $inCart = array_flip($query->cartProductIds);
        $scores = [];
        $evidence = [];
        $map = $this->aggregates->frequentlyBoughtTogetherMap();

        foreach ($query->cartProductIds as $cartProductId) {
            foreach ($map[$cartProductId] ?? [] as $related) {
                $productId = (int) $related['product_id'];

                if (isset($inCart[$productId])) {
                    continue;
                }

                $scores[$productId] = max($scores[$productId] ?? 0.0, (float) $related['order_count'] + 1.0);
                $evidence[$productId] = ['from_cart_product_id' => $cartProductId];
            }
        }

        $candidates = [];

        foreach ($scores as $productId => $score) {
            $candidates[$productId] = new RecommendationCandidate(
                productId: (int) $productId,
                strategy: $this->key(),
                reason: RecommendationReason::CompleteYourOrder,
                baseScore: $score,
                evidence: $evidence[$productId] ?? [],
            );
        }

        // Category complements when FBT evidence is thin.
        if ($candidates === []) {
            $categories = Product::query()
                ->whereIn('id', $query->cartProductIds)
                ->pluck('product_category_id')
                ->filter()
                ->unique()
                ->all();

            if ($categories !== []) {
                $related = Product::query()
                    ->where('is_active', true)
                    ->where('is_available', true)
                    ->whereNull('deleted_at')
                    ->whereIn('product_category_id', $categories)
                    ->whereNotIn('id', $query->cartProductIds)
                    ->orderByDesc('is_bestseller')
                    ->limit(12)
                    ->pluck('id');

                foreach ($related as $index => $productId) {
                    $candidates[(int) $productId] = new RecommendationCandidate(
                        productId: (int) $productId,
                        strategy: $this->key(),
                        reason: RecommendationReason::CompleteYourOrder,
                        baseScore: 3.0 - ($index * 0.1),
                        evidence: ['via' => 'cart_category'],
                    );
                }
            }
        }

        return array_values($candidates);
    }
}
