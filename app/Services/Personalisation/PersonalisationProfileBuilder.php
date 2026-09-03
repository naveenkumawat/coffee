<?php

namespace App\Services\Personalisation;

use App\Enums\BehaviourEventType;
use App\Models\CustomerBehaviourEvent;
use App\Models\Order;
use App\Repositories\Personalisation\PersonalisationProfileRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Deterministic V1 personalisation profile builder.
 *
 * Purchase evidence comes only from canonical completed orders.
 * Behaviour event type order_completed is ignored to avoid double counting.
 */
class PersonalisationProfileBuilder
{
    public function __construct(
        protected PersonalisationProfileRepositoryInterface $profiles,
    ) {}

    /**
     * @param  Collection<int, CustomerBehaviourEvent>  $events
     * @param  Collection<int, Order>  $orders
     * @return array<string, mixed>
     */
    public function build(Collection $events, Collection $orders, ?CarbonInterface $now = null): array
    {
        $now = Carbon::parse($now ?? now());
        $config = $this->config();
        $weights = $config['weights'];
        $halfLife = max(0.1, (float) $config['recency_half_life_days']);
        $maxRepeats = max(1, (int) $config['max_repeats_per_signal']);
        $topN = max(1, (int) $config['top_n']);
        $recentN = max(1, (int) $config['recent_n']);
        $minEvidence = max(1, (int) $config['min_evidence_signals']);

        $categoryScores = [];
        $productScores = [];
        $variantScores = [];
        $addonScores = [];
        $timeScores = [
            'morning' => 0.0,
            'afternoon' => 0.0,
            'evening' => 0.0,
            'night' => 0.0,
        ];
        $productSignalCounts = [];
        $categorySignalCounts = [];
        $variantSignalCounts = [];
        $addonSignalCounts = [];
        $recentProducts = [];
        $recentCategories = [];
        $lastActivity = null;
        $evidenceSignals = 0;

        $timezone = (string) config('coffee.timezone', 'Asia/Kolkata');

        foreach ($events as $event) {
            $type = $event->event_type instanceof BehaviourEventType
                ? $event->event_type
                : BehaviourEventType::tryFrom((string) $event->event_type);

            if ($type === null || $type === BehaviourEventType::OrderCompleted) {
                continue;
            }

            $weight = $this->eventWeight($type, $weights);

            if ($weight == 0.0) {
                continue;
            }

            $occurredAt = Carbon::parse($event->occurred_at)->timezone($timezone);
            $decay = $this->recencyDecay($occurredAt, $now, $halfLife);
            $evidenceSignals++;

            if ($lastActivity === null || $occurredAt->gt($lastActivity)) {
                $lastActivity = $occurredAt->copy();
            }

            $period = $this->timePeriod($occurredAt, $config['time_of_day']);
            $timeScores[$period] = ($timeScores[$period] ?? 0.0) + abs($weight) * $decay;

            if ($event->product_id) {
                $productId = (int) $event->product_id;
                $key = 'product:'.$productId.':'.$type->value;
                $productSignalCounts[$key] = ($productSignalCounts[$key] ?? 0) + 1;
                $count = $productSignalCounts[$key];
                $factor = $this->repeatFactor($count, $maxRepeats);
                $delta = $weight * $decay * $factor;
                $productScores[$productId] = ($productScores[$productId] ?? 0.0) + $delta;
                $recentProducts[$productId] = $occurredAt->getTimestamp();
            }

            if ($event->product_category_id) {
                $categoryId = (int) $event->product_category_id;
                $key = 'category:'.$categoryId.':'.$type->value;
                $categorySignalCounts[$key] = ($categorySignalCounts[$key] ?? 0) + 1;
                $count = $categorySignalCounts[$key];
                $factor = $this->repeatFactor($count, $maxRepeats);
                $delta = $weight * $decay * $factor;
                $categoryScores[$categoryId] = ($categoryScores[$categoryId] ?? 0.0) + $delta;
                $recentCategories[$categoryId] = $occurredAt->getTimestamp();
            } elseif ($type === BehaviourEventType::CategoryViewed) {
                // category_viewed without category id contributes nothing else
            }

            if ($event->product_variant_id && $event->product_id) {
                $variantId = (int) $event->product_variant_id;
                $key = 'variant:'.$variantId.':'.$type->value;
                $variantSignalCounts[$key] = ($variantSignalCounts[$key] ?? 0) + 1;
                $factor = $this->repeatFactor($variantSignalCounts[$key], $maxRepeats);
                $delta = $weight * $decay * $factor;
                $variantScores[$variantId] = [
                    'score' => ($variantScores[$variantId]['score'] ?? 0.0) + $delta,
                    'product_id' => (int) $event->product_id,
                ];
            }

            $metadata = is_array($event->metadata) ? $event->metadata : [];

            if (isset($metadata['addon_ids']) && is_array($metadata['addon_ids'])) {
                foreach (array_slice($metadata['addon_ids'], 0, 20) as $addonId) {
                    $addonId = (int) $addonId;

                    if ($addonId <= 0) {
                        continue;
                    }

                    $key = 'addon:'.$addonId.':'.$type->value;
                    $addonSignalCounts[$key] = ($addonSignalCounts[$key] ?? 0) + 1;
                    $factor = $this->repeatFactor($addonSignalCounts[$key], $maxRepeats);
                    $addonScores[$addonId] = ($addonScores[$addonId] ?? 0.0) + ($weight * $decay * $factor);
                }
            }
        }

        $purchaseWeight = (float) ($weights['purchase_item'] ?? 10.0);
        $purchaseProductCounts = [];
        $orderTotals = [];
        $orderTimestamps = [];

        foreach ($orders as $order) {
            $completedAt = Carbon::parse($order->completed_at ?? $order->updated_at ?? $order->created_at)
                ->timezone($timezone);
            $decay = $this->recencyDecay($completedAt, $now, $halfLife);
            $evidenceSignals++;
            $orderTotals[] = (float) $order->total_amount;
            $orderTimestamps[] = $completedAt->getTimestamp();

            if ($lastActivity === null || $completedAt->gt($lastActivity)) {
                $lastActivity = $completedAt->copy();
            }

            $period = $this->timePeriod($completedAt, $config['time_of_day']);
            $timeScores[$period] = ($timeScores[$period] ?? 0.0) + $purchaseWeight * $decay;

            foreach ($order->items as $item) {
                $qty = max(1, (int) $item->quantity);
                $productId = (int) $item->product_id;
                $delta = $purchaseWeight * $decay * $qty;
                $productScores[$productId] = ($productScores[$productId] ?? 0.0) + $delta;
                $purchaseProductCounts[$productId] = ($purchaseProductCounts[$productId] ?? 0) + $qty;
                $recentProducts[$productId] = max($recentProducts[$productId] ?? 0, $completedAt->getTimestamp());

                $categoryId = $item->product?->product_category_id
                    ? (int) $item->product->product_category_id
                    : null;

                if ($categoryId) {
                    $categoryScores[$categoryId] = ($categoryScores[$categoryId] ?? 0.0) + $delta;
                    $recentCategories[$categoryId] = max($recentCategories[$categoryId] ?? 0, $completedAt->getTimestamp());
                }

                if ($item->product_variant_id) {
                    $variantId = (int) $item->product_variant_id;
                    $variantScores[$variantId] = [
                        'score' => ($variantScores[$variantId]['score'] ?? 0.0) + $delta,
                        'product_id' => $productId,
                    ];
                }

                foreach ($item->addOns as $addOn) {
                    $addonId = (int) $addOn->add_on_id;

                    if ($addonId <= 0) {
                        continue;
                    }

                    $addonQty = max(1, (int) ($addOn->quantity ?? 1));
                    $addonScores[$addonId] = ($addonScores[$addonId] ?? 0.0) + ($purchaseWeight * $decay * $addonQty);
                }
            }
        }

        $flavourScores = $this->deriveFlavourScores($productScores, $this->profiles->flavourIdsByProductIds(array_keys($productScores)));

        $categoryAffinities = $this->rankScores($categoryScores, $topN);
        $productAffinities = $this->rankScores($productScores, $topN);
        $flavourAffinities = $this->rankScores($flavourScores, $topN);
        $preferredVariants = $this->rankVariants($variantScores, $topN);
        $addonPreferences = $this->rankScores($addonScores, $topN);
        $timePreferences = $this->rankNamedScores($timeScores, 4);

        arsort($recentProducts);
        arsort($recentCategories);

        $repeatPurchaseIds = [];

        foreach ($purchaseProductCounts as $productId => $count) {
            if ($count >= 2) {
                $repeatPurchaseIds[] = (int) $productId;
            }
        }
        sort($repeatPurchaseIds);

        return [
            'profile_version' => (int) $config['version'],
            'event_sample_count' => $events->count(),
            'order_sample_count' => $orders->count(),
            'has_sufficient_evidence' => $evidenceSignals >= $minEvidence,
            'last_activity_at' => $lastActivity,
            'calculated_at' => $now,
            'category_affinities' => $categoryAffinities,
            'product_affinities' => $productAffinities,
            'flavour_affinities' => $flavourAffinities,
            'preferred_variants' => $preferredVariants,
            'addon_preferences' => $addonPreferences,
            'recent_product_ids' => array_map('intval', array_slice(array_keys($recentProducts), 0, $recentN)),
            'recent_category_ids' => array_map('intval', array_slice(array_keys($recentCategories), 0, $recentN)),
            'purchase_frequency' => $this->purchaseFrequency($orders, $orderTimestamps, $config, $now),
            'repeat_purchase_product_ids' => $repeatPurchaseIds,
            'spend_band' => $this->spendBand($orderTotals, $config),
            'time_of_day_preferences' => $timePreferences,
            'signals_meta' => [
                'algorithm' => 'v1_weighted_recency',
                'weights_version' => (int) $config['version'],
                'evidence_signals' => $evidenceSignals,
                'recency_half_life_days' => $halfLife,
                'max_repeats_per_signal' => $maxRepeats,
                'purchase_source' => 'canonical_completed_orders',
                'excludes_behaviour_order_completed' => true,
            ],
        ];
    }

    /**
     * @param  array<string, float|int>  $weights
     */
    protected function eventWeight(BehaviourEventType $type, array $weights): float
    {
        return match ($type) {
            BehaviourEventType::FavouriteAdded => (float) ($weights['favourite_added'] ?? 5.0),
            BehaviourEventType::FavouriteRemoved => (float) ($weights['favourite_removed'] ?? -1.5),
            BehaviourEventType::CartItemAdded => (float) ($weights['cart_item_added'] ?? 3.0),
            BehaviourEventType::CartItemRemoved => (float) ($weights['cart_item_removed'] ?? -0.5),
            BehaviourEventType::ProductCustomized => (float) ($weights['product_customized'] ?? 2.5),
            BehaviourEventType::ProductViewed => (float) ($weights['product_viewed'] ?? 1.0),
            BehaviourEventType::CategoryViewed => (float) ($weights['category_viewed'] ?? 1.0),
            default => 0.0,
        };
    }

    protected function recencyDecay(CarbonInterface $occurredAt, CarbonInterface $now, float $halfLifeDays): float
    {
        $ageDays = max(0.0, $occurredAt->diffInSeconds($now, false) / 86400);

        if ($ageDays < 0) {
            $ageDays = 0.0;
        }

        return 0.5 ** ($ageDays / $halfLifeDays);
    }

    protected function repeatFactor(int $occurrenceIndex, int $maxRepeats): float
    {
        if ($occurrenceIndex > $maxRepeats) {
            return 0.0;
        }

        // Diminishing returns within the cap: 1, 1/2, 1/3, ...
        return 1.0 / $occurrenceIndex;
    }

    /**
     * @param  array{morning: array{0: int, 1: int}, afternoon: array{0: int, 1: int}, evening: array{0: int, 1: int}, night: array{0: int, 1: int}}  $windows
     */
    protected function timePeriod(CarbonInterface $at, array $windows): string
    {
        $hour = (int) $at->format('G');

        foreach (['morning', 'afternoon', 'evening'] as $period) {
            [$start, $end] = $windows[$period] ?? [0, 0];

            if ($hour >= (int) $start && $hour < (int) $end) {
                return $period;
            }
        }

        return 'night';
    }

    /**
     * @param  array<int|string, float>  $scores
     * @return list<array{id: int, score: float}>
     */
    protected function rankScores(array $scores, int $topN): array
    {
        $filtered = [];

        foreach ($scores as $id => $score) {
            if ($score > 0) {
                $filtered[(int) $id] = round((float) $score, 4);
            }
        }

        arsort($filtered);

        $ranked = [];

        foreach (array_slice($filtered, 0, $topN, true) as $id => $score) {
            $ranked[] = ['id' => (int) $id, 'score' => $score];
        }

        return $ranked;
    }

    /**
     * @param  array<int, array{score: float, product_id: int}>  $variantScores
     * @return list<array{id: int, product_id: int, score: float}>
     */
    protected function rankVariants(array $variantScores, int $topN): array
    {
        uasort($variantScores, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        $ranked = [];

        foreach ($variantScores as $variantId => $row) {
            if ($row['score'] <= 0) {
                continue;
            }

            $ranked[] = [
                'id' => (int) $variantId,
                'product_id' => (int) $row['product_id'],
                'score' => round((float) $row['score'], 4),
            ];

            if (count($ranked) >= $topN) {
                break;
            }
        }

        return $ranked;
    }

    /**
     * @param  array<string, float>  $scores
     * @return list<array{period: string, score: float}>
     */
    protected function rankNamedScores(array $scores, int $topN): array
    {
        $filtered = [];

        foreach ($scores as $name => $score) {
            if ($score > 0) {
                $filtered[(string) $name] = round((float) $score, 4);
            }
        }

        arsort($filtered);

        $ranked = [];

        foreach (array_slice($filtered, 0, $topN, true) as $period => $score) {
            $ranked[] = ['period' => $period, 'score' => $score];
        }

        return $ranked;
    }

    /**
     * @param  array<int, float>  $productScores
     * @param  array<int, list<int>>  $flavoursByProduct
     * @return array<int, float>
     */
    protected function deriveFlavourScores(array $productScores, array $flavoursByProduct): array
    {
        $flavourScores = [];

        foreach ($productScores as $productId => $score) {
            if ($score <= 0) {
                continue;
            }

            foreach ($flavoursByProduct[(int) $productId] ?? [] as $flavourId) {
                $flavourScores[$flavourId] = ($flavourScores[$flavourId] ?? 0.0) + (float) $score;
            }
        }

        return $flavourScores;
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @param  list<int>  $orderTimestamps
     * @param  array<string, mixed>  $config
     * @return array{orders_count: int, days_span: int|null, orders_per_30d: float|null, sufficient: bool}
     */
    protected function purchaseFrequency(Collection $orders, array $orderTimestamps, array $config, CarbonInterface $now): array
    {
        $count = $orders->count();
        $minOrders = max(1, (int) $config['min_orders_for_frequency']);

        if ($count < $minOrders || $orderTimestamps === []) {
            return [
                'orders_count' => $count,
                'days_span' => null,
                'orders_per_30d' => null,
                'sufficient' => false,
            ];
        }

        sort($orderTimestamps);
        $first = Carbon::createFromTimestamp($orderTimestamps[0]);
        $daysSpan = max(1, (int) ceil($first->diffInDays($now)));
        $per30 = round(($count / $daysSpan) * 30, 3);

        return [
            'orders_count' => $count,
            'days_span' => $daysSpan,
            'orders_per_30d' => $per30,
            'sufficient' => true,
        ];
    }

    /**
     * @param  list<float>  $totals
     * @param  array<string, mixed>  $config
     * @return array{band: ?string, avg_order_total: ?float, sample_orders: int, sufficient: bool}
     */
    protected function spendBand(array $totals, array $config): array
    {
        $sample = count($totals);
        $minOrders = max(1, (int) $config['min_orders_for_spend_band']);

        if ($sample < $minOrders) {
            return [
                'band' => null,
                'avg_order_total' => null,
                'sample_orders' => $sample,
                'sufficient' => false,
            ];
        }

        $avg = round(array_sum($totals) / $sample, 2);
        $band = null;

        foreach ($config['spend_bands'] as $definition) {
            $max = $definition['max'];

            if ($max === null || $avg <= (float) $max) {
                $band = (string) $definition['key'];
                break;
            }
        }

        return [
            'band' => $band,
            'avg_order_total' => $avg,
            'sample_orders' => $sample,
            'sufficient' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function config(): array
    {
        return (array) config('coffee.behaviour.profile', []);
    }
}
