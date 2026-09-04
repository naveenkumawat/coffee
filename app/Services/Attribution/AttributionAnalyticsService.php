<?php

namespace App\Services\Attribution;

use App\Enums\AttributionFunnelStage;
use App\Enums\AttributionSourceType;
use App\Enums\BehaviourEventType;
use App\Models\Campaign;
use App\Models\CommerceAttributionEvent;
use App\Models\CustomerBehaviourEvent;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttributionAnalyticsService implements AttributionAnalyticsServiceInterface
{
    public const PRESET_TODAY = 'today';

    public const PRESET_YESTERDAY = 'yesterday';

    public const PRESET_LAST_7_DAYS = 'last_7_days';

    public const PRESET_THIS_MONTH = 'this_month';

    public const PRESET_CUSTOM = 'custom';

    public function buildRecommendationReport(array $filters = []): array
    {
        $period = $this->resolvePeriod($filters);

        if (app()->environment('testing')) {
            $computed = $this->computeRecommendationReport($period);
        } else {
            $cacheKey = 'attr.rec.'.$period['preset'].'.'.$period['start_utc']->timestamp.'.'.$period['end_utc']->timestamp;
            $ttl = max(15, (int) config('coffee.behaviour.attribution.analytics_cache_ttl_seconds', 60));
            /** @var array<string, mixed> $computed */
            $computed = Cache::remember($cacheKey, $ttl, function () use ($period): array {
                return $this->computeRecommendationReport($period);
            });
        }

        return array_merge($computed, [
            'timezone' => $period['timezone'],
            'preset' => $period['preset'],
            'start_local' => $period['start_local'],
            'end_local' => $period['end_local'],
            'start_utc' => $period['start_utc'],
            'end_utc' => $period['end_utc'],
        ]);
    }

    public function buildCampaignReport(array $filters = []): array
    {
        $period = $this->resolvePeriod($filters);

        if (app()->environment('testing')) {
            $computed = $this->computeCampaignReport($period);
        } else {
            $cacheKey = 'attr.camp.'.$period['preset'].'.'.$period['start_utc']->timestamp.'.'.$period['end_utc']->timestamp;
            $ttl = max(15, (int) config('coffee.behaviour.attribution.analytics_cache_ttl_seconds', 60));
            /** @var array<string, mixed> $computed */
            $computed = Cache::remember($cacheKey, $ttl, function () use ($period): array {
                return $this->computeCampaignReport($period);
            });
        }

        return array_merge($computed, [
            'timezone' => $period['timezone'],
            'preset' => $period['preset'],
            'start_local' => $period['start_local'],
            'end_local' => $period['end_local'],
            'start_utc' => $period['start_utc'],
            'end_utc' => $period['end_utc'],
        ]);
    }

    /**
     * @param  array{
     *     timezone: string,
     *     preset: string,
     *     start_local: CarbonImmutable,
     *     end_local: CarbonImmutable,
     *     start_utc: CarbonImmutable,
     *     end_utc: CarbonImmutable
     * }  $period
     * @return array<string, mixed>
     */
    protected function computeRecommendationReport(array $period): array
    {
        $start = $period['start_utc'];
        $end = $period['end_utc'];

        $impressions = $this->countBehaviour(BehaviourEventType::RecommendationImpression, $start, $end);
        $clicks = $this->countBehaviour(BehaviourEventType::RecommendationClicked, $start, $end);
        $cartAdds = $this->sumFunnel(AttributionSourceType::Recommendation, AttributionFunnelStage::CartAdded, $start, $end);
        $conversions = $this->sumFunnel(AttributionSourceType::Recommendation, AttributionFunnelStage::Converted, $start, $end);

        $byStrategy = $this->strategyBreakdown($start, $end);
        $byPlacement = $this->placementBreakdown(AttributionSourceType::Recommendation, $start, $end);
        $byProduct = $this->productBreakdown(AttributionSourceType::Recommendation, $start, $end);

        return [
            'summary' => [
                'impressions' => $impressions,
                'clicks' => $clicks,
                'ctr' => $this->rate($clicks, $impressions),
                'attributed_cart_additions' => $cartAdds['events'],
                'attributed_orders' => $conversions['orders'],
                'attributed_units' => $conversions['units'],
                'attributed_revenue' => $conversions['revenue'],
                'click_to_purchase_rate' => $this->rate($conversions['events'], $clicks),
                'impression_to_purchase_rate' => $this->rate($conversions['events'], $impressions),
            ],
            'strategies' => $byStrategy,
            'placements' => $byPlacement,
            'products' => $byProduct,
            'disclaimer' => 'Attributed conversion metrics measure correlation with recommendation exposure/clicks — not causal proof.',
        ];
    }

    /**
     * @param  array{
     *     timezone: string,
     *     preset: string,
     *     start_local: CarbonImmutable,
     *     end_local: CarbonImmutable,
     *     start_utc: CarbonImmutable,
     *     end_utc: CarbonImmutable
     * }  $period
     * @return array<string, mixed>
     */
    protected function computeCampaignReport(array $period): array
    {
        $start = $period['start_utc'];
        $end = $period['end_utc'];

        $impressions = $this->countBehaviour(BehaviourEventType::CampaignImpression, $start, $end);
        $clicks = $this->countBehaviour(BehaviourEventType::CampaignClicked, $start, $end);
        $dismissals = $this->countBehaviour(BehaviourEventType::CampaignDismissed, $start, $end);
        $uniqueActors = $this->uniqueCampaignActors($start, $end);
        $cartAdds = $this->sumFunnel(AttributionSourceType::Campaign, AttributionFunnelStage::CartAdded, $start, $end);
        $conversions = $this->sumFunnel(AttributionSourceType::Campaign, AttributionFunnelStage::Converted, $start, $end);

        return [
            'summary' => [
                'impressions' => $impressions,
                'unique_actors' => $uniqueActors,
                'clicks' => $clicks,
                'dismissals' => $dismissals,
                'ctr' => $this->rate($clicks, $impressions),
                'attributed_cart_additions' => $cartAdds['events'],
                'attributed_orders' => $conversions['orders'],
                'attributed_units' => $conversions['units'],
                'attributed_revenue' => $conversions['revenue'],
                'conversion_rate' => $this->rate($conversions['events'], $impressions),
            ],
            'campaigns' => $this->campaignBreakdown($start, $end),
            'placements' => $this->placementBreakdown(AttributionSourceType::Campaign, $start, $end),
            'disclaimer' => 'Attributed conversion metrics measure correlation with campaign exposure/clicks — not causal proof. Promotion discounts remain in Financial Report.',
        ];
    }

    /**
     * @return array{events: int, orders: int, units: int, revenue: string}
     */
    protected function sumFunnel(
        AttributionSourceType $sourceType,
        AttributionFunnelStage $stage,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): array {
        $row = CommerceAttributionEvent::query()
            ->where('source_type', $sourceType->value)
            ->where('stage', $stage->value)
            ->whereBetween('occurred_at', [$start, $end])
            ->selectRaw('COUNT(*) as events')
            ->selectRaw('COUNT(DISTINCT order_id) as orders')
            ->selectRaw('COALESCE(SUM(units), 0) as units')
            ->selectRaw('COALESCE(SUM(revenue_amount), 0) as revenue')
            ->first();

        return [
            'events' => (int) ($row->events ?? 0),
            'orders' => (int) ($row->orders ?? 0),
            'units' => (int) ($row->units ?? 0),
            'revenue' => number_format((float) ($row->revenue ?? 0), 2, '.', ''),
        ];
    }

    protected function countBehaviour(
        BehaviourEventType $type,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): int {
        return (int) CustomerBehaviourEvent::query()
            ->where('event_type', $type->value)
            ->whereBetween('occurred_at', [$start, $end])
            ->count();
    }

    protected function uniqueCampaignActors(CarbonImmutable $start, CarbonImmutable $end): int
    {
        $customers = (int) CustomerBehaviourEvent::query()
            ->where('event_type', BehaviourEventType::CampaignImpression->value)
            ->whereBetween('occurred_at', [$start, $end])
            ->whereNotNull('customer_id')
            ->distinct()
            ->count('customer_id');

        $visitors = (int) CustomerBehaviourEvent::query()
            ->where('event_type', BehaviourEventType::CampaignImpression->value)
            ->whereBetween('occurred_at', [$start, $end])
            ->whereNull('customer_id')
            ->whereNotNull('visitor_key')
            ->distinct()
            ->count('visitor_key');

        return $customers + $visitors;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function strategyBreakdown(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $clickRows = CustomerBehaviourEvent::query()
            ->where('event_type', BehaviourEventType::RecommendationClicked->value)
            ->whereBetween('occurred_at', [$start, $end])
            ->selectRaw('COALESCE('.$this->jsonText('metadata', 'strategy').", 'unknown') as strategy")
            ->selectRaw('COUNT(*) as clicks')
            ->groupBy('strategy')
            ->pluck('clicks', 'strategy');

        $impressionRows = CustomerBehaviourEvent::query()
            ->where('event_type', BehaviourEventType::RecommendationImpression->value)
            ->whereBetween('occurred_at', [$start, $end])
            ->selectRaw('COALESCE('.$this->jsonText('metadata', 'strategy').", 'unknown') as strategy")
            ->selectRaw('COUNT(*) as impressions')
            ->groupBy('strategy')
            ->pluck('impressions', 'strategy');

        $conversionRows = CommerceAttributionEvent::query()
            ->where('source_type', AttributionSourceType::Recommendation->value)
            ->where('stage', AttributionFunnelStage::Converted->value)
            ->whereBetween('occurred_at', [$start, $end])
            ->selectRaw("COALESCE(strategy, 'unknown') as strategy")
            ->selectRaw('COUNT(*) as conversions')
            ->selectRaw('COALESCE(SUM(units), 0) as units')
            ->selectRaw('COALESCE(SUM(revenue_amount), 0) as revenue')
            ->groupBy('strategy')
            ->get()
            ->keyBy('strategy');

        $cartRows = CommerceAttributionEvent::query()
            ->where('source_type', AttributionSourceType::Recommendation->value)
            ->where('stage', AttributionFunnelStage::CartAdded->value)
            ->whereBetween('occurred_at', [$start, $end])
            ->selectRaw("COALESCE(strategy, 'unknown') as strategy")
            ->selectRaw('COUNT(*) as cart_adds')
            ->groupBy('strategy')
            ->pluck('cart_adds', 'strategy');

        $keys = collect($clickRows->keys())
            ->merge($impressionRows->keys())
            ->merge($conversionRows->keys())
            ->merge($cartRows->keys())
            ->unique()
            ->sort()
            ->values();

        return $keys->map(function (string $strategy) use ($clickRows, $impressionRows, $conversionRows, $cartRows): array {
            $impressions = (int) ($impressionRows[$strategy] ?? 0);
            $clicks = (int) ($clickRows[$strategy] ?? 0);
            $conv = $conversionRows->get($strategy);

            return [
                'strategy' => $strategy,
                'impressions' => $impressions,
                'clicks' => $clicks,
                'ctr' => $this->rate($clicks, $impressions),
                'cart_adds' => (int) ($cartRows[$strategy] ?? 0),
                'conversions' => (int) ($conv->conversions ?? 0),
                'units' => (int) ($conv->units ?? 0),
                'revenue' => number_format((float) ($conv->revenue ?? 0), 2, '.', ''),
                'click_to_purchase_rate' => $this->rate((int) ($conv->conversions ?? 0), $clicks),
            ];
        })->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function placementBreakdown(
        AttributionSourceType $sourceType,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): array {
        $eventType = $sourceType === AttributionSourceType::Campaign
            ? BehaviourEventType::CampaignImpression
            : BehaviourEventType::RecommendationImpression;

        $impressions = CustomerBehaviourEvent::query()
            ->where('event_type', $eventType->value)
            ->whereBetween('occurred_at', [$start, $end])
            ->selectRaw('COALESCE('.$this->jsonText('metadata', 'placement').", 'unknown') as placement")
            ->selectRaw('COUNT(*) as impressions')
            ->groupBy('placement')
            ->pluck('impressions', 'placement');

        $conversions = CommerceAttributionEvent::query()
            ->where('source_type', $sourceType->value)
            ->where('stage', AttributionFunnelStage::Converted->value)
            ->whereBetween('occurred_at', [$start, $end])
            ->selectRaw("COALESCE(placement, 'unknown') as placement")
            ->selectRaw('COUNT(*) as conversions')
            ->selectRaw('COALESCE(SUM(revenue_amount), 0) as revenue')
            ->groupBy('placement')
            ->get()
            ->keyBy('placement');

        $keys = collect($impressions->keys())->merge($conversions->keys())->unique()->sort()->values();

        return $keys->map(function (string $placement) use ($impressions, $conversions): array {
            $imp = (int) ($impressions[$placement] ?? 0);
            $conv = $conversions->get($placement);

            return [
                'placement' => $placement,
                'impressions' => $imp,
                'conversions' => (int) ($conv->conversions ?? 0),
                'revenue' => number_format((float) ($conv->revenue ?? 0), 2, '.', ''),
                'conversion_rate' => $this->rate((int) ($conv->conversions ?? 0), $imp),
            ];
        })->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function productBreakdown(
        AttributionSourceType $sourceType,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): array {
        $rows = CommerceAttributionEvent::query()
            ->where('source_type', $sourceType->value)
            ->where('stage', AttributionFunnelStage::Converted->value)
            ->whereBetween('occurred_at', [$start, $end])
            ->whereNotNull('product_id')
            ->selectRaw('product_id')
            ->selectRaw('COUNT(*) as conversions')
            ->selectRaw('COALESCE(SUM(units), 0) as units')
            ->selectRaw('COALESCE(SUM(revenue_amount), 0) as revenue')
            ->groupBy('product_id')
            ->orderByDesc('revenue')
            ->limit(15)
            ->get();

        $names = Product::query()
            ->whereIn('id', $rows->pluck('product_id')->all())
            ->pluck('name', 'id');

        return $rows->map(fn ($row): array => [
            'product_id' => (int) $row->product_id,
            'product_name' => (string) ($names[$row->product_id] ?? ('#'.$row->product_id)),
            'conversions' => (int) $row->conversions,
            'units' => (int) $row->units,
            'revenue' => number_format((float) $row->revenue, 2, '.', ''),
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function campaignBreakdown(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $impressions = CustomerBehaviourEvent::query()
            ->where('event_type', BehaviourEventType::CampaignImpression->value)
            ->whereBetween('occurred_at', [$start, $end])
            ->selectRaw($this->castJsonPathAsInteger('metadata', 'campaign_id').' as campaign_id')
            ->selectRaw('COUNT(*) as impressions')
            ->groupBy('campaign_id')
            ->pluck('impressions', 'campaign_id');

        $clicks = CustomerBehaviourEvent::query()
            ->where('event_type', BehaviourEventType::CampaignClicked->value)
            ->whereBetween('occurred_at', [$start, $end])
            ->selectRaw($this->castJsonPathAsInteger('metadata', 'campaign_id').' as campaign_id')
            ->selectRaw('COUNT(*) as clicks')
            ->groupBy('campaign_id')
            ->pluck('clicks', 'campaign_id');

        $dismissals = CustomerBehaviourEvent::query()
            ->where('event_type', BehaviourEventType::CampaignDismissed->value)
            ->whereBetween('occurred_at', [$start, $end])
            ->selectRaw($this->castJsonPathAsInteger('metadata', 'campaign_id').' as campaign_id')
            ->selectRaw('COUNT(*) as dismissals')
            ->groupBy('campaign_id')
            ->pluck('dismissals', 'campaign_id');

        $conversions = CommerceAttributionEvent::query()
            ->where('source_type', AttributionSourceType::Campaign->value)
            ->where('stage', AttributionFunnelStage::Converted->value)
            ->whereBetween('occurred_at', [$start, $end])
            ->selectRaw('source_id as campaign_id')
            ->selectRaw('COUNT(*) as conversions')
            ->selectRaw('COALESCE(SUM(units), 0) as units')
            ->selectRaw('COALESCE(SUM(revenue_amount), 0) as revenue')
            ->groupBy('source_id')
            ->get()
            ->keyBy('campaign_id');

        $cartAdds = CommerceAttributionEvent::query()
            ->where('source_type', AttributionSourceType::Campaign->value)
            ->where('stage', AttributionFunnelStage::CartAdded->value)
            ->whereBetween('occurred_at', [$start, $end])
            ->selectRaw('source_id as campaign_id')
            ->selectRaw('COUNT(*) as cart_adds')
            ->groupBy('source_id')
            ->pluck('cart_adds', 'campaign_id');

        $ids = collect($impressions->keys())
            ->merge($clicks->keys())
            ->merge($conversions->keys())
            ->filter(fn ($id) => (int) $id > 0)
            ->unique()
            ->values();

        $names = Campaign::query()->whereIn('id', $ids->all())->pluck('name', 'id');

        return $ids->map(function ($id) use ($impressions, $clicks, $dismissals, $conversions, $cartAdds, $names): array {
            $campaignId = (int) $id;
            $imp = (int) ($impressions[$campaignId] ?? $impressions[(string) $campaignId] ?? 0);
            $clk = (int) ($clicks[$campaignId] ?? $clicks[(string) $campaignId] ?? 0);
            $conv = $conversions->get($campaignId) ?? $conversions->get((string) $campaignId);

            return [
                'campaign_id' => $campaignId,
                'campaign_name' => (string) ($names[$campaignId] ?? ('#'.$campaignId)),
                'impressions' => $imp,
                'clicks' => $clk,
                'dismissals' => (int) ($dismissals[$campaignId] ?? $dismissals[(string) $campaignId] ?? 0),
                'ctr' => $this->rate($clk, $imp),
                'cart_adds' => (int) ($cartAdds[$campaignId] ?? $cartAdds[(string) $campaignId] ?? 0),
                'conversions' => (int) ($conv->conversions ?? 0),
                'units' => (int) ($conv->units ?? 0),
                'revenue' => number_format((float) ($conv->revenue ?? 0), 2, '.', ''),
                'conversion_rate' => $this->rate((int) ($conv->conversions ?? 0), $imp),
            ];
        })->sortByDesc('impressions')->values()->all();
    }

    protected function rate(int $numerator, int $denominator): ?float
    {
        if ($denominator <= 0) {
            return null;
        }

        return round(($numerator / $denominator) * 100, 2);
    }

    protected function jsonText(string $column, string $path): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "json_extract({$column}, '$.{$path}')",
            default => "JSON_UNQUOTE(JSON_EXTRACT({$column}, '$.{$path}'))",
        };
    }

    /**
     * MySQL uses SIGNED/UNSIGNED for integer casts; SQLite accepts INTEGER.
     */
    protected function castJsonPathAsInteger(string $column, string $path): string
    {
        $expression = $this->jsonText($column, $path);

        return match (DB::connection()->getDriverName()) {
            'sqlite' => "CAST({$expression} AS INTEGER)",
            default => "CAST({$expression} AS UNSIGNED)",
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     timezone: string,
     *     preset: string,
     *     start_local: CarbonImmutable,
     *     end_local: CarbonImmutable,
     *     start_utc: CarbonImmutable,
     *     end_utc: CarbonImmutable
     * }
     */
    protected function resolvePeriod(array $filters): array
    {
        $timezone = (string) config('app.timezone', 'UTC');
        $preset = (string) ($filters['preset'] ?? self::PRESET_TODAY);
        $nowLocal = CarbonImmutable::now($timezone);

        [$startLocal, $endLocal] = match ($preset) {
            self::PRESET_YESTERDAY => [
                $nowLocal->subDay()->startOfDay(),
                $nowLocal->subDay()->endOfDay(),
            ],
            self::PRESET_LAST_7_DAYS => [
                $nowLocal->subDays(6)->startOfDay(),
                $nowLocal->endOfDay(),
            ],
            self::PRESET_THIS_MONTH => [
                $nowLocal->startOfMonth()->startOfDay(),
                $nowLocal->endOfDay(),
            ],
            self::PRESET_CUSTOM => $this->customRange($filters, $timezone),
            default => [
                $nowLocal->startOfDay(),
                $nowLocal->endOfDay(),
            ],
        };

        if ($preset !== self::PRESET_CUSTOM) {
            $preset = in_array($preset, [
                self::PRESET_TODAY,
                self::PRESET_YESTERDAY,
                self::PRESET_LAST_7_DAYS,
                self::PRESET_THIS_MONTH,
            ], true) ? $preset : self::PRESET_TODAY;
        }

        return [
            'timezone' => $timezone,
            'preset' => $preset,
            'start_local' => $startLocal,
            'end_local' => $endLocal,
            'start_utc' => $startLocal->setTimezone('UTC'),
            'end_utc' => $endLocal->setTimezone('UTC'),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    protected function customRange(array $filters, string $timezone): array
    {
        $from = (string) ($filters['from'] ?? '');
        $to = (string) ($filters['to'] ?? '');

        if ($from === '' || $to === '') {
            throw ValidationException::withMessages([
                'from' => 'Custom range requires from and to dates.',
            ]);
        }

        $start = CarbonImmutable::createFromFormat('Y-m-d', $from, $timezone)?->startOfDay();
        $end = CarbonImmutable::createFromFormat('Y-m-d', $to, $timezone)?->endOfDay();

        if ($start === null || $end === null || $end->lt($start)) {
            throw ValidationException::withMessages([
                'to' => 'Invalid custom date range.',
            ]);
        }

        return [$start, $end];
    }
}
