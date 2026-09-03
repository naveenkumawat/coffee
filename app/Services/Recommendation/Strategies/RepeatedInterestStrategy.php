<?php

namespace App\Services\Recommendation\Strategies;

use App\Enums\BehaviourEventType;
use App\Enums\RecommendationReason;
use App\Services\Recommendation\Contracts\RecommendationStrategyInterface;
use App\Services\Recommendation\Support\RecommendationCandidate;
use App\Services\Recommendation\Support\RecommendationQuery;
use Illuminate\Support\Facades\DB;

class RepeatedInterestStrategy implements RecommendationStrategyInterface
{
    public function key(): string
    {
        return 'repeated_interest';
    }

    public function candidates(RecommendationQuery $query): array
    {
        $events = DB::table('customer_behaviour_events')
            ->select(['product_id', 'event_type', 'occurred_at'])
            ->whereNotNull('product_id')
            ->whereIn('event_type', [
                BehaviourEventType::ProductViewed->value,
                BehaviourEventType::ProductCustomized->value,
                BehaviourEventType::CartItemAdded->value,
                BehaviourEventType::FavouriteAdded->value,
            ])
            ->when(
                $query->customer !== null,
                fn ($q) => $q->where('customer_id', $query->customer->getKey()),
                fn ($q) => $q->where('visitor_key', $query->visitorKey)->whereNull('customer_id'),
            )
            ->where('occurred_at', '>=', now()->subDays(60))
            ->orderBy('occurred_at')
            ->get();

        if ($events->isEmpty() || ($query->customer === null && blank($query->visitorKey))) {
            return [];
        }

        $byProduct = [];

        foreach ($events as $event) {
            $productId = (int) $event->product_id;
            $day = substr((string) $event->occurred_at, 0, 10);
            $byProduct[$productId]['days'][$day] = true;
            $byProduct[$productId]['views'] = ($byProduct[$productId]['views'] ?? 0)
                + ($event->event_type === BehaviourEventType::ProductViewed->value ? 1 : 0);
            $byProduct[$productId]['engage'] = ($byProduct[$productId]['engage'] ?? 0)
                + ($event->event_type !== BehaviourEventType::ProductViewed->value ? 1 : 0);
        }

        $candidates = [];

        foreach ($byProduct as $productId => $stats) {
            $distinctDays = count($stats['days'] ?? []);
            $views = (int) ($stats['views'] ?? 0);
            $engage = (int) ($stats['engage'] ?? 0);

            // Distinct-day interest outweighs same-session view spam.
            $score = ($distinctDays * 4.0) + min(3.0, $views * 0.25) + ($engage * 1.5);

            if ($distinctDays < 2 && $engage < 1) {
                continue;
            }

            $candidates[] = new RecommendationCandidate(
                productId: (int) $productId,
                strategy: $this->key(),
                reason: $distinctDays >= 2
                    ? RecommendationReason::BecauseYouViewed
                    : RecommendationReason::BasedOnYourInterests,
                baseScore: $score,
                evidence: [
                    'distinct_days' => $distinctDays,
                    'views' => $views,
                    'engagements' => $engage,
                ],
            );
        }

        usort($candidates, static fn (RecommendationCandidate $a, RecommendationCandidate $b): int => $b->baseScore <=> $a->baseScore);

        return array_slice($candidates, 0, 20);
    }
}
