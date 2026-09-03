<?php

namespace App\Services\Recommendation\Strategies;

use App\Enums\OrderStatus;
use App\Enums\RecommendationReason;
use App\Services\Recommendation\Contracts\RecommendationStrategyInterface;
use App\Services\Recommendation\Support\RecommendationCandidate;
use App\Services\Recommendation\Support\RecommendationQuery;
use Illuminate\Support\Facades\DB;

class BuyAgainStrategy implements RecommendationStrategyInterface
{
    public function key(): string
    {
        return 'buy_again';
    }

    public function candidates(RecommendationQuery $query): array
    {
        if ($query->customer === null) {
            return [];
        }

        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->selectRaw('order_items.product_id, COUNT(DISTINCT orders.id) as order_count, SUM(order_items.quantity) as quantity, MAX(orders.completed_at) as last_purchased_at')
            ->where('orders.customer_id', $query->customer->getKey())
            ->where('orders.status', OrderStatus::Completed->value)
            ->groupBy('order_items.product_id')
            ->orderByDesc('order_count')
            ->orderByDesc('last_purchased_at')
            ->limit(20)
            ->get();

        $candidates = [];

        foreach ($rows as $row) {
            $orderCount = (int) $row->order_count;
            $qty = (int) $row->quantity;
            $recencyBoost = $row->last_purchased_at ? 1.0 : 0.5;
            $score = ($orderCount * 3.0) + ($qty * 0.5) + $recencyBoost;

            $candidates[] = new RecommendationCandidate(
                productId: (int) $row->product_id,
                strategy: $this->key(),
                reason: RecommendationReason::BuyAgain,
                baseScore: $score,
                evidence: [
                    'order_count' => $orderCount,
                    'quantity' => $qty,
                ],
            );
        }

        return $candidates;
    }
}
