<?php

namespace App\Services\Recommendation;

use App\Enums\RecommendationContext;
use App\Enums\UserRole;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Product;
use App\Models\User;
use App\Services\Personalisation\PersonalisationProfileServiceInterface;
use App\Services\Recommendation\Contracts\RecommendationStrategyInterface;
use App\Services\Recommendation\Strategies\AffinityStrategy;
use App\Services\Recommendation\Strategies\BestsellerStrategy;
use App\Services\Recommendation\Strategies\BuyAgainStrategy;
use App\Services\Recommendation\Strategies\CartContextStrategy;
use App\Services\Recommendation\Strategies\FavouriteStrategy;
use App\Services\Recommendation\Strategies\FeaturedStrategy;
use App\Services\Recommendation\Strategies\FrequentlyBoughtTogetherStrategy;
use App\Services\Recommendation\Strategies\NewArrivalStrategy;
use App\Services\Recommendation\Strategies\PopularStrategy;
use App\Services\Recommendation\Strategies\RepeatedInterestStrategy;
use App\Services\Recommendation\Strategies\SimilarProductsStrategy;
use App\Services\Recommendation\Strategies\TrendingStrategy;
use App\Services\Recommendation\Support\RecommendationCandidate;
use App\Services\Recommendation\Support\RecommendationQuery;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RecommendationService implements RecommendationServiceInterface
{
    /** @var array<string, RecommendationStrategyInterface> */
    protected array $strategies;

    public function __construct(
        protected PersonalisationProfileServiceInterface $profiles,
        BuyAgainStrategy $buyAgain,
        FavouriteStrategy $favourite,
        RepeatedInterestStrategy $repeatedInterest,
        AffinityStrategy $affinity,
        SimilarProductsStrategy $similar,
        TrendingStrategy $trending,
        PopularStrategy $popular,
        NewArrivalStrategy $newArrival,
        FeaturedStrategy $featured,
        BestsellerStrategy $bestseller,
        FrequentlyBoughtTogetherStrategy $fbt,
        CartContextStrategy $cartContext,
    ) {
        $this->strategies = [
            $buyAgain->key() => $buyAgain,
            $favourite->key() => $favourite,
            $repeatedInterest->key() => $repeatedInterest,
            $affinity->key() => $affinity,
            $similar->key() => $similar,
            $trending->key() => $trending,
            $popular->key() => $popular,
            $newArrival->key() => $newArrival,
            $featured->key() => $featured,
            $bestseller->key() => $bestseller,
            $fbt->key() => $fbt,
            $cartContext->key() => $cartContext,
        ];
    }

    /**
     * @param  array{
     *     context: string,
     *     visitor_key?: string|null,
     *     product_id?: int|null,
     *     category_id?: int|null,
     *     cart_product_ids?: list<int>,
     *     exclude_product_ids?: list<int>,
     *     limit?: int|null
     * }  $input
     * @return array{
     *     request_id: string,
     *     context: string,
     *     cold_start: bool,
     *     items: list<array{product: array<string, mixed>, reason: string, strategy: string, request_id: string}>
     * }
     */
    public function recommend(array $input, ?User $customer = null): array
    {
        $context = RecommendationContext::from((string) $input['context']);
        $limit = max(1, min(
            (int) ($input['limit'] ?? config('coffee.behaviour.recommendations.default_limit', 8)),
            (int) config('coffee.behaviour.recommendations.max_limit', 16),
        ));

        $customer = $customer !== null && $customer->hasRole(UserRole::Customer) ? $customer : null;
        $visitorKey = isset($input['visitor_key']) ? trim((string) $input['visitor_key']) : null;
        $visitorKey = $visitorKey !== '' ? $visitorKey : null;

        $profile = null;
        $hasEvidence = false;

        if ($customer !== null) {
            $profile = $this->profiles->profilePayloadForCustomer((int) $customer->getKey());
            $hasEvidence = (bool) ($profile['has_sufficient_evidence'] ?? false);
        } elseif ($visitorKey !== null) {
            $profile = $this->profiles->profilePayloadForVisitor($visitorKey);
            $hasEvidence = (bool) ($profile['has_sufficient_evidence'] ?? false);
        }

        $exclude = array_values(array_unique(array_filter(array_map(
            'intval',
            array_merge(
                $input['exclude_product_ids'] ?? [],
                $input['cart_product_ids'] ?? [],
                $input['product_id'] ?? null ? [(int) $input['product_id']] : [],
            ),
        ))));

        $query = new RecommendationQuery(
            context: $context,
            customer: $customer,
            visitorKey: $visitorKey,
            productId: isset($input['product_id']) ? (int) $input['product_id'] : null,
            categoryId: isset($input['category_id']) ? (int) $input['category_id'] : null,
            cartProductIds: array_values(array_unique(array_filter(array_map('intval', $input['cart_product_ids'] ?? [])))),
            excludeProductIds: $exclude,
            limit: $limit,
            profile: $profile,
            hasSufficientEvidence: $hasEvidence,
        );

        $strategyKeys = is_array($input['strategies'] ?? null) && $input['strategies'] !== []
            ? array_values(array_map('strval', $input['strategies']))
            : $this->strategiesFor($context, $hasEvidence);
        $candidates = [];

        foreach ($strategyKeys as $key) {
            $strategy = $this->strategies[$key] ?? null;

            if ($strategy === null) {
                continue;
            }

            foreach ($strategy->candidates($query) as $candidate) {
                $candidates[] = $candidate;
            }
        }

        $ranked = $this->rankAndDiversify($candidates, $query);
        $products = $this->loadEligibleProducts(array_column($ranked, 'product_id'));
        $requestId = (string) Str::uuid();

        $items = [];

        foreach ($ranked as $row) {
            $product = $products->get($row['product_id']);

            if ($product === null) {
                continue;
            }

            $items[] = [
                'product' => (new ProductResource($product))->resolve(),
                'reason' => $row['reason'],
                'strategy' => $row['strategy'],
                'request_id' => $requestId,
            ];

            if (count($items) >= $limit) {
                break;
            }
        }

        return [
            'request_id' => $requestId,
            'context' => $context->value,
            'cold_start' => ! $hasEvidence,
            'items' => $items,
        ];
    }

    /**
     * @return list<string>
     */
    protected function strategiesFor(RecommendationContext $context, bool $hasEvidence): array
    {
        $configured = config('coffee.behaviour.recommendations.context_strategies.'.$context->value);

        if (is_array($configured) && $configured !== []) {
            return array_values($configured);
        }

        $key = $hasEvidence ? 'warm_strategies' : 'cold_start_strategies';

        return array_values((array) config('coffee.behaviour.recommendations.'.$key, []));
    }

    /**
     * @param  list<RecommendationCandidate>  $candidates
     * @return list<array{product_id: int, reason: string, strategy: string, final_score: float}>
     */
    protected function rankAndDiversify(array $candidates, RecommendationQuery $query): array
    {
        $weights = (array) config('coffee.behaviour.recommendations.strategy_weights', []);
        $priorities = (array) config('coffee.behaviour.recommendations.strategy_priorities', [
            'buy_again' => 100,
            'favourite' => 95,
            'repeated_interest' => 85,
            'frequently_bought_together' => 80,
            'cart_context' => 78,
            'affinity' => 70,
            'similar' => 55,
            'trending' => 45,
            'popular' => 40,
            'bestseller' => 35,
            'new_arrival' => 30,
            'featured' => 30,
        ]);
        $exclude = array_flip($query->excludeProductIds);
        $best = [];

        foreach ($candidates as $candidate) {
            if (isset($exclude[$candidate->productId])) {
                continue;
            }

            $weight = (float) ($weights[$candidate->strategy] ?? 1.0);
            $final = $candidate->baseScore * $weight;
            $priority = (int) ($priorities[$candidate->strategy] ?? 0);
            $existing = $best[$candidate->productId] ?? null;

            if ($existing === null) {
                $best[$candidate->productId] = [
                    'product_id' => $candidate->productId,
                    'reason' => $candidate->reason->value,
                    'strategy' => $candidate->strategy,
                    'final_score' => $final,
                    'priority' => $priority,
                ];

                continue;
            }

            // Keep strongest ranking score, but prefer higher-intent strategy for the customer-facing reason.
            $best[$candidate->productId]['final_score'] = max($existing['final_score'], $final);

            if ($priority > (int) $existing['priority']) {
                $best[$candidate->productId]['reason'] = $candidate->reason->value;
                $best[$candidate->productId]['strategy'] = $candidate->strategy;
                $best[$candidate->productId]['priority'] = $priority;
            }
        }

        uasort($best, static fn (array $a, array $b): int => $b['final_score'] <=> $a['final_score']);

        $maxPerCategory = max(1, (int) config('coffee.behaviour.recommendations.max_per_category', 3));
        $categoryCounts = [];
        $diversified = [];
        $productCategories = Product::query()
            ->whereIn('id', array_keys($best))
            ->pluck('product_category_id', 'id');

        foreach ($best as $row) {
            $categoryId = (int) ($productCategories[$row['product_id']] ?? 0);
            $count = $categoryCounts[$categoryId] ?? 0;

            // Allow stronger intent strategies to exceed soft diversity slightly.
            $strongIntent = in_array($row['strategy'], ['buy_again', 'favourite', 'cart_context', 'frequently_bought_together'], true);

            if ($categoryId > 0 && $count >= $maxPerCategory && ! $strongIntent) {
                continue;
            }

            $categoryCounts[$categoryId] = $count + 1;
            $diversified[] = $row;
        }

        return $diversified;
    }

    /**
     * @param  list<int>  $productIds
     * @return Collection<int, Product>
     */
    protected function loadEligibleProducts(array $productIds): Collection
    {
        $productIds = array_values(array_unique(array_filter($productIds)));

        if ($productIds === []) {
            return collect();
        }

        return Product::query()
            ->with([
                'category',
                'flavours',
                'tags' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')->orderBy('name'),
                'defaultVariant',
                'variants' => fn ($q) => $q->where('is_active', true)->where('is_available', true),
                'addOns' => fn ($q) => $q->where('add_ons.is_active', true)->orderByPivot('sort_order'),
            ])
            ->withAvg('ratings as ratings_avg_rating', 'rating')
            ->withCount('ratings')
            ->whereIn('id', $productIds)
            ->where('is_active', true)
            ->where('is_available', true)
            ->whereNull('deleted_at')
            ->whereHas('category', fn ($q) => $q->where('is_active', true))
            ->get()
            ->keyBy('id');
    }
}
