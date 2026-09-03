<?php

namespace App\Services\Recommendation\Strategies;

use App\Enums\RecommendationReason;
use App\Models\Product;
use App\Services\Recommendation\Contracts\RecommendationStrategyInterface;
use App\Services\Recommendation\Support\RecommendationCandidate;
use App\Services\Recommendation\Support\RecommendationQuery;
use Illuminate\Support\Facades\DB;

class SimilarProductsStrategy implements RecommendationStrategyInterface
{
    public function key(): string
    {
        return 'similar';
    }

    public function candidates(RecommendationQuery $query): array
    {
        $seedIds = [];

        if ($query->productId) {
            $seedIds[] = $query->productId;
        }

        foreach ($query->profile['recent_product_ids'] ?? [] as $id) {
            $seedIds[] = (int) $id;
        }

        foreach ($query->profile['product_affinities'] ?? [] as $row) {
            $seedIds[] = (int) ($row['id'] ?? 0);
        }

        if ($query->categoryId) {
            // Category browsing context.
            $categoryProducts = DB::table('products')
                ->where('product_category_id', $query->categoryId)
                ->where('is_active', true)
                ->where('is_available', true)
                ->whereNull('deleted_at')
                ->orderByDesc('is_bestseller')
                ->orderByDesc('is_featured')
                ->limit(12)
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all();

            $candidates = [];

            foreach ($categoryProducts as $index => $productId) {
                if ($query->productId && $productId === $query->productId) {
                    continue;
                }

                $candidates[] = new RecommendationCandidate(
                    productId: $productId,
                    strategy: $this->key(),
                    reason: RecommendationReason::SimilarProduct,
                    baseScore: 5.0 - ($index * 0.1),
                    evidence: ['via' => 'category', 'category_id' => $query->categoryId],
                );
            }

            return $candidates;
        }

        $seedIds = array_values(array_unique(array_filter(array_map('intval', $seedIds))));

        if ($seedIds === []) {
            return [];
        }

        $seeds = Product::query()
            ->with(['flavours:id'])
            ->whereIn('id', array_slice($seedIds, 0, 5))
            ->get(['id', 'product_category_id']);

        if ($seeds->isEmpty()) {
            return [];
        }

        $categoryIds = $seeds->pluck('product_category_id')->filter()->unique()->values()->all();
        $flavourIds = $seeds->flatMap(fn (Product $p) => $p->flavours->pluck('id'))->unique()->values()->all();
        $exclude = array_flip($seedIds);

        $related = Product::query()
            ->where('is_active', true)
            ->where('is_available', true)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($categoryIds, $flavourIds): void {
                if ($categoryIds !== []) {
                    $q->whereIn('product_category_id', $categoryIds);
                }

                if ($flavourIds !== []) {
                    $q->orWhereHas('flavours', fn ($fq) => $fq->whereIn('product_flavours.id', $flavourIds));
                }
            })
            ->orderByDesc('is_bestseller')
            ->orderByDesc('is_featured')
            ->limit(30)
            ->get(['id', 'product_category_id']);

        $candidates = [];

        foreach ($related as $index => $product) {
            $productId = (int) $product->id;

            if (isset($exclude[$productId])) {
                continue;
            }

            $candidates[] = new RecommendationCandidate(
                productId: $productId,
                strategy: $this->key(),
                reason: RecommendationReason::SimilarProduct,
                baseScore: 5.0 - ($index * 0.05),
                evidence: ['via' => 'catalog_similarity'],
            );
        }

        return array_slice($candidates, 0, 20);
    }
}
