<?php

namespace Database\Factories;

use App\Enums\AttributionFunnelStage;
use App\Enums\AttributionMode;
use App\Enums\AttributionSourceType;
use App\Models\CommerceAttributionEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CommerceAttributionEvent>
 */
class CommerceAttributionEventFactory extends Factory
{
    protected $model = CommerceAttributionEvent::class;

    public function definition(): array
    {
        $requestId = (string) Str::uuid();

        return [
            'source_type' => AttributionSourceType::Recommendation,
            'source_id' => null,
            'request_id' => $requestId,
            'product_id' => null,
            'product_variant_id' => null,
            'customer_id' => null,
            'visitor_key' => 'guest'.Str::lower(Str::random(10)),
            'strategy' => 'popular',
            'reason' => 'popular',
            'placement' => 'home',
            'context' => 'home',
            'attribution_mode' => AttributionMode::Direct,
            'stage' => AttributionFunnelStage::CartAdded,
            'order_id' => null,
            'order_item_id' => null,
            'units' => 1,
            'revenue_amount' => null,
            'idempotency_key' => 'test:'.$requestId.':'.Str::lower(Str::random(6)),
            'occurred_at' => now(),
        ];
    }
}
