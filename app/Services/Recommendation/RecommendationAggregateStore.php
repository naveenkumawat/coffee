<?php

namespace App\Services\Recommendation;

use App\Enums\BehaviourEventType;
use App\Enums\OrderStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Cached global aggregate signals for trending / popular / FBT / new arrivals.
 * Payloads are JSON-safe arrays so cache hit/miss shapes stay identical.
 */
class RecommendationAggregateStore
{
    /**
     * @return list<array{product_id: int, actors: int, score: float}>
     */
    public function trendingProducts(): array
    {
        $ttl = max(30, (int) config('coffee.behaviour.recommendations.cache_ttl_seconds', 300));
        $days = max(1, (int) config('coffee.behaviour.recommendations.trending_lookback_days', 14));
        $minActors = max(1, (int) config('coffee.behaviour.recommendations.trending_min_actors', 2));

        return Cache::remember('recommendations.trending.v1.'.$days.'.'.$minActors, $ttl, function () use ($days, $minActors): array {
            $since = now()->subDays($days);
            $weights = [
                BehaviourEventType::ProductViewed->value => 1.0,
                BehaviourEventType::ProductCustomized->value => 2.0,
                BehaviourEventType::CartItemAdded->value => 3.0,
                BehaviourEventType::FavouriteAdded->value => 4.0,
            ];

            $actorExpr = "CASE WHEN customer_id IS NOT NULL THEN CONCAT('c', customer_id) ELSE CONCAT('v', visitor_key) END";

            $rows = DB::table('customer_behaviour_events')
                ->selectRaw("product_id, COUNT(DISTINCT {$actorExpr}) as actors")
                ->selectRaw('SUM(CASE event_type '
                    .'WHEN ? THEN 1.0 WHEN ? THEN 2.0 WHEN ? THEN 3.0 WHEN ? THEN 4.0 ELSE 0 END) as raw_score', [
                        BehaviourEventType::ProductViewed->value,
                        BehaviourEventType::ProductCustomized->value,
                        BehaviourEventType::CartItemAdded->value,
                        BehaviourEventType::FavouriteAdded->value,
                    ])
                ->whereNotNull('product_id')
                ->where('occurred_at', '>=', $since)
                ->whereIn('event_type', array_keys($weights))
                ->groupBy('product_id')
                ->havingRaw("COUNT(DISTINCT {$actorExpr}) >= ?", [$minActors])
                ->orderByDesc('raw_score')
                ->limit(50)
                ->get();

            // Also blend recent completed-order momentum (distinct customers).
            $orderRows = DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->selectRaw('order_items.product_id, COUNT(DISTINCT orders.customer_id) as buyers, SUM(order_items.quantity) as qty')
                ->where('orders.status', OrderStatus::Completed->value)
                ->where('orders.completed_at', '>=', $since)
                ->groupBy('order_items.product_id')
                ->havingRaw('COUNT(DISTINCT orders.customer_id) >= ?', [$minActors])
                ->orderByDesc('qty')
                ->limit(50)
                ->get()
                ->keyBy('product_id');

            $merged = [];

            foreach ($rows as $row) {
                $productId = (int) $row->product_id;
                $merged[$productId] = [
                    'product_id' => $productId,
                    'actors' => (int) $row->actors,
                    'score' => round((float) $row->raw_score, 4),
                ];
            }

            foreach ($orderRows as $productId => $row) {
                $productId = (int) $productId;
                $boost = ((float) $row->qty) * 2.0;
                if (! isset($merged[$productId])) {
                    $merged[$productId] = [
                        'product_id' => $productId,
                        'actors' => (int) $row->buyers,
                        'score' => round($boost, 4),
                    ];
                } else {
                    $merged[$productId]['score'] = round($merged[$productId]['score'] + $boost, 4);
                    $merged[$productId]['actors'] = max($merged[$productId]['actors'], (int) $row->buyers);
                }
            }

            usort($merged, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

            return array_values(array_slice($merged, 0, 40));
        });
    }

    /**
     * @return list<array{product_id: int, order_count: int, quantity: int}>
     */
    public function popularProducts(): array
    {
        $ttl = max(30, (int) config('coffee.behaviour.recommendations.cache_ttl_seconds', 300));
        $days = max(1, (int) config('coffee.behaviour.recommendations.popular_lookback_days', 90));
        $minOrders = max(1, (int) config('coffee.behaviour.recommendations.popular_min_orders', 2));

        return Cache::remember('recommendations.popular.v1.'.$days.'.'.$minOrders, $ttl, function () use ($days, $minOrders): array {
            $since = now()->subDays($days);

            return DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->selectRaw('order_items.product_id, COUNT(DISTINCT orders.id) as order_count, SUM(order_items.quantity) as quantity')
                ->where('orders.status', OrderStatus::Completed->value)
                ->where(function ($query) use ($since): void {
                    $query->where('orders.completed_at', '>=', $since)
                        ->orWhere(function ($inner) use ($since): void {
                            $inner->whereNull('orders.completed_at')
                                ->where('orders.updated_at', '>=', $since);
                        });
                })
                ->groupBy('order_items.product_id')
                ->havingRaw('COUNT(DISTINCT orders.id) >= ?', [$minOrders])
                ->orderByDesc('order_count')
                ->orderByDesc('quantity')
                ->limit(40)
                ->get()
                ->map(static fn ($row): array => [
                    'product_id' => (int) $row->product_id,
                    'order_count' => (int) $row->order_count,
                    'quantity' => (int) $row->quantity,
                ])
                ->all();
        });
    }

    /**
     * @return array<int, list<array{product_id: int, order_count: int}>>
     */
    public function frequentlyBoughtTogetherMap(): array
    {
        $ttl = max(30, (int) config('coffee.behaviour.recommendations.cache_ttl_seconds', 300));
        $days = max(1, (int) config('coffee.behaviour.recommendations.fbt_lookback_days', 180));
        $minOrders = max(1, (int) config('coffee.behaviour.recommendations.fbt_min_orders', 3));

        return Cache::remember('recommendations.fbt.v1.'.$days.'.'.$minOrders, $ttl, function () use ($days, $minOrders): array {
            $since = now()->subDays($days);

            $pairs = DB::table('order_items as a')
                ->join('order_items as b', function ($join): void {
                    $join->on('a.order_id', '=', 'b.order_id')
                        ->whereColumn('a.product_id', '<', 'b.product_id');
                })
                ->join('orders', 'orders.id', '=', 'a.order_id')
                ->selectRaw('a.product_id as product_a, b.product_id as product_b, COUNT(DISTINCT a.order_id) as order_count')
                ->where('orders.status', OrderStatus::Completed->value)
                ->where('orders.completed_at', '>=', $since)
                ->groupBy('a.product_id', 'b.product_id')
                ->havingRaw('COUNT(DISTINCT a.order_id) >= ?', [$minOrders])
                ->orderByDesc('order_count')
                ->limit(500)
                ->get();

            $map = [];

            foreach ($pairs as $pair) {
                $a = (int) $pair->product_a;
                $b = (int) $pair->product_b;
                $count = (int) $pair->order_count;
                $map[$a][] = ['product_id' => $b, 'order_count' => $count];
                $map[$b][] = ['product_id' => $a, 'order_count' => $count];
            }

            foreach ($map as $productId => $related) {
                usort($related, static fn (array $x, array $y): int => $y['order_count'] <=> $x['order_count']);
                $map[$productId] = array_slice($related, 0, 12);
            }

            return $map;
        });
    }

    /**
     * @return list<int>
     */
    public function newArrivalProductIds(): array
    {
        $ttl = max(30, (int) config('coffee.behaviour.recommendations.cache_ttl_seconds', 300));
        $days = max(1, (int) config('coffee.behaviour.recommendations.new_arrival_days', 30));

        return Cache::remember('recommendations.new.v1.'.$days, $ttl, function () use ($days): array {
            $since = now()->subDays($days);

            return DB::table('products')
                ->where('is_active', true)
                ->where('is_available', true)
                ->whereNull('deleted_at')
                ->where(function ($query) use ($since): void {
                    $query->where('created_at', '>=', $since)
                        ->orWhere('is_new', true);
                })
                ->orderByDesc('created_at')
                ->limit(40)
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all();
        });
    }

    /**
     * @return list<int>
     */
    public function featuredProductIds(): array
    {
        $ttl = max(30, (int) config('coffee.behaviour.recommendations.cache_ttl_seconds', 300));

        return Cache::remember('recommendations.featured.v1', $ttl, function (): array {
            return DB::table('products')
                ->where('is_active', true)
                ->where('is_available', true)
                ->whereNull('deleted_at')
                ->where('is_featured', true)
                ->orderByDesc('updated_at')
                ->limit(40)
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all();
        });
    }

    /**
     * @return list<int>
     */
    public function bestsellerTaggedProductIds(): array
    {
        $ttl = max(30, (int) config('coffee.behaviour.recommendations.cache_ttl_seconds', 300));

        return Cache::remember('recommendations.bestseller_tag.v1', $ttl, function (): array {
            return DB::table('products')
                ->where('is_active', true)
                ->where('is_available', true)
                ->whereNull('deleted_at')
                ->where('is_bestseller', true)
                ->orderByDesc('updated_at')
                ->limit(40)
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all();
        });
    }

    public function flush(): void
    {
        // Versioned keys are lookback-dependent; forget common prefixes via known keys.
        $daysT = (int) config('coffee.behaviour.recommendations.trending_lookback_days', 14);
        $minA = (int) config('coffee.behaviour.recommendations.trending_min_actors', 2);
        $daysP = (int) config('coffee.behaviour.recommendations.popular_lookback_days', 90);
        $minO = (int) config('coffee.behaviour.recommendations.popular_min_orders', 2);
        $daysF = (int) config('coffee.behaviour.recommendations.fbt_lookback_days', 180);
        $minF = (int) config('coffee.behaviour.recommendations.fbt_min_orders', 3);
        $daysN = (int) config('coffee.behaviour.recommendations.new_arrival_days', 30);

        Cache::forget('recommendations.trending.v1.'.$daysT.'.'.$minA);
        Cache::forget('recommendations.popular.v1.'.$daysP.'.'.$minO);
        Cache::forget('recommendations.fbt.v1.'.$daysF.'.'.$minF);
        Cache::forget('recommendations.new.v1.'.$daysN);
        Cache::forget('recommendations.featured.v1');
        Cache::forget('recommendations.bestseller_tag.v1');
    }
}
