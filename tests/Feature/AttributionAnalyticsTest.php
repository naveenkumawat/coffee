<?php

namespace Tests\Feature;

use App\Enums\AttributionFunnelStage;
use App\Enums\AttributionSourceType;
use App\Enums\BehaviourEventSource;
use App\Enums\BehaviourEventType;
use App\Enums\CampaignCtaType;
use App\Enums\CampaignFrequencyPolicy;
use App\Enums\CampaignStatus;
use App\Enums\CampaignSurface;
use App\Enums\CampaignTriggerType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\Order\OrderStatusChanged;
use App\Models\Campaign;
use App\Models\CommerceAttributionEvent;
use App\Models\CustomerBehaviourEvent;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Attribution\AttributionAnalyticsServiceInterface;
use App\Services\Attribution\AttributionServiceInterface;
use App\Services\Cart\CartServiceInterface;
use App\Transfers\Cart\CartItemTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AttributionAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_recommendation_impression_click_cart_order_attribution(): void
    {
        $customer = User::factory()->customer()->create();
        $product = $this->makePublicProduct('Rec Drink');
        $variant = $product->variants()->first();
        $requestId = (string) Str::uuid();

        $this->recordRecClick($customer, null, $product, $requestId, 'buy_again', 'home');

        $transfer = new CartItemTransfer;
        $transfer->setProductVariantId((int) $variant->id);
        $transfer->setQuantity(1);
        $transfer->setAttribution([
            'source_type' => 'recommendation',
            'request_id' => $requestId,
            'strategy' => 'buy_again',
            'reason' => 'buy_again',
            'placement' => 'home',
        ]);

        $cart = app(CartServiceInterface::class)->addItem($customer, $transfer);
        $item = $cart->items->first();
        $this->assertNotNull($item->attribution);
        $this->assertSame('recommendation', $item->attribution['source_type']);
        $this->assertDatabaseHas('commerce_attribution_events', [
            'stage' => AttributionFunnelStage::CartAdded->value,
            'request_id' => $requestId,
        ]);

        $order = $this->placeAttributedOrder($customer, $item);
        $this->assertNotNull($order->items->first()->attribution);
        $this->assertSame($requestId, $order->items->first()->attribution['request_id']);

        $order->update([
            'status' => OrderStatus::Completed,
            'payment_status' => PaymentStatus::Confirmed,
            'completed_at' => now(),
        ]);
        event(new OrderStatusChanged($order->fresh(), OrderStatus::Accepted, OrderStatus::Completed));

        $this->assertDatabaseHas('commerce_attribution_events', [
            'stage' => AttributionFunnelStage::Converted->value,
            'order_id' => $order->id,
            'request_id' => $requestId,
        ]);
        $this->assertDatabaseHas('customer_behaviour_events', [
            'event_type' => BehaviourEventType::RecommendationConverted->value,
            'order_id' => $order->id,
        ]);

        // Idempotent
        event(new OrderStatusChanged($order->fresh(), OrderStatus::Accepted, OrderStatus::Completed));
        $this->assertSame(
            1,
            CommerceAttributionEvent::query()
                ->where('stage', AttributionFunnelStage::Converted->value)
                ->where('order_id', $order->id)
                ->count(),
        );
    }

    public function test_campaign_direct_attribution_and_guest_continuity(): void
    {
        $visitor = 'guest'.Str::lower(Str::random(10));
        $customer = User::factory()->customer()->create();
        $product = $this->makePublicProduct('Camp Drink');
        $variant = $product->variants()->first();
        $campaign = Campaign::factory()->active()->popup()->create([
            'status' => CampaignStatus::Active,
            'surface' => CampaignSurface::Popup,
            'cta_type' => CampaignCtaType::Product,
            'cta_product_id' => $product->id,
            'frequency_policy' => CampaignFrequencyPolicy::EverySession,
            'placement_rules' => ['placements' => ['home'], 'category_ids' => [], 'product_ids' => [], 'product_tag_ids' => []],
            'targeting_rules' => ['all' => [['type' => 'identity', 'op' => 'eq', 'value' => 'everyone']], 'any' => [], 'exclude' => []],
            'trigger_rules' => ['type' => CampaignTriggerType::Immediate->value],
        ]);
        $requestId = (string) Str::uuid();

        CustomerBehaviourEvent::query()->create([
            'event_type' => BehaviourEventType::CampaignClicked->value,
            'source' => BehaviourEventSource::Client->value,
            'visitor_key' => $visitor,
            'product_id' => $product->id,
            'occurred_at' => now(),
            'metadata' => [
                'campaign_id' => $campaign->id,
                'request_id' => $requestId,
                'placement' => 'home',
            ],
        ]);

        $transfer = new CartItemTransfer;
        $transfer->setProductVariantId((int) $variant->id);
        $transfer->setQuantity(1);
        $transfer->setVisitorKey($visitor);
        $transfer->setAttribution([
            'source_type' => 'campaign',
            'source_id' => $campaign->id,
            'request_id' => $requestId,
            'placement' => 'home',
        ]);

        // Guest continuity: visitor evidence still matches after auth cart add.
        $cart = app(CartServiceInterface::class)->addItem($customer, $transfer);
        $this->assertSame('campaign', $cart->items->first()->attribution['source_type']);
    }

    public function test_unattributed_purchase_and_forged_attribution_rejected(): void
    {
        $customer = User::factory()->customer()->create();
        $product = $this->makePublicProduct('Plain');
        $variant = $product->variants()->first();

        $transfer = new CartItemTransfer;
        $transfer->setProductVariantId((int) $variant->id);
        $transfer->setQuantity(1);
        $cart = app(CartServiceInterface::class)->addItem($customer, $transfer);
        $this->assertNull($cart->items->first()->attribution);

        $forged = new CartItemTransfer;
        $forged->setProductVariantId((int) $variant->id);
        $forged->setQuantity(1);
        $forged->setAttribution([
            'source_type' => 'recommendation',
            'request_id' => (string) Str::uuid(),
            'strategy' => 'hack',
            'placement' => 'home',
        ]);

        $this->expectException(ValidationException::class);
        app(CartServiceInterface::class)->addItem($customer, $forged);
    }

    public function test_cancelled_and_unpaid_orders_do_not_convert(): void
    {
        $customer = User::factory()->customer()->create();
        $product = $this->makePublicProduct('Skip');
        $variant = $product->variants()->first();
        $requestId = (string) Str::uuid();
        $this->recordRecClick($customer, null, $product, $requestId, 'popular', 'home');

        $transfer = new CartItemTransfer;
        $transfer->setProductVariantId((int) $variant->id);
        $transfer->setQuantity(1);
        $transfer->setAttribution([
            'source_type' => 'recommendation',
            'request_id' => $requestId,
            'strategy' => 'popular',
            'placement' => 'home',
        ]);
        $cart = app(CartServiceInterface::class)->addItem($customer, $transfer);
        $order = $this->placeAttributedOrder($customer, $cart->items->first());

        $order->update(['status' => OrderStatus::Cancelled, 'payment_status' => PaymentStatus::Pending]);
        app(AttributionServiceInterface::class)->recordConversionsForOrder($order->fresh(['items']));
        $this->assertSame(0, CommerceAttributionEvent::query()->where('stage', 'converted')->count());

        $order->update(['status' => OrderStatus::Completed, 'payment_status' => PaymentStatus::Pending, 'completed_at' => now()]);
        app(AttributionServiceInterface::class)->recordConversionsForOrder($order->fresh(['items']));
        $this->assertSame(0, CommerceAttributionEvent::query()->where('stage', 'converted')->count());
    }

    public function test_snapshot_stable_when_campaign_changes_and_pruning_behaviour(): void
    {
        $customer = User::factory()->customer()->create();
        $product = $this->makePublicProduct('Stable');
        $variant = $product->variants()->first();
        $requestId = (string) Str::uuid();
        $this->recordRecClick($customer, null, $product, $requestId, 'featured', 'menu');

        $transfer = new CartItemTransfer;
        $transfer->setProductVariantId((int) $variant->id);
        $transfer->setQuantity(2);
        $transfer->setAttribution([
            'source_type' => 'recommendation',
            'request_id' => $requestId,
            'strategy' => 'featured',
            'placement' => 'menu',
        ]);
        $cart = app(CartServiceInterface::class)->addItem($customer, $transfer);
        $order = $this->placeAttributedOrder($customer, $cart->items->first());
        $snap = $order->items->first()->attribution;

        CustomerBehaviourEvent::query()->delete();
        $this->assertSame($requestId, $order->fresh(['items'])->items->first()->attribution['request_id']);
        $this->assertSame($snap['strategy'], $order->fresh(['items'])->items->first()->attribution['strategy']);
    }

    public function test_analytics_aggregation_zero_denominator_and_date_filter(): void
    {
        $empty = app(AttributionAnalyticsServiceInterface::class)->buildRecommendationReport([
            'preset' => 'today',
        ]);
        $this->assertSame(0, $empty['summary']['impressions']);
        $this->assertNull($empty['summary']['ctr']);
        $this->assertNull($empty['summary']['click_to_purchase_rate']);

        $customer = User::factory()->customer()->create();
        $product = $this->makePublicProduct('Agg');
        $requestId = (string) Str::uuid();

        CustomerBehaviourEvent::query()->create([
            'event_type' => BehaviourEventType::RecommendationImpression->value,
            'source' => BehaviourEventSource::Client->value,
            'customer_id' => $customer->id,
            'product_id' => $product->id,
            'occurred_at' => now(),
            'metadata' => ['request_id' => $requestId, 'strategy' => 'trending', 'placement' => 'home', 'reason' => 'trending'],
        ]);
        CustomerBehaviourEvent::query()->create([
            'event_type' => BehaviourEventType::RecommendationClicked->value,
            'source' => BehaviourEventSource::Client->value,
            'customer_id' => $customer->id,
            'product_id' => $product->id,
            'occurred_at' => now(),
            'metadata' => ['request_id' => $requestId, 'strategy' => 'trending', 'placement' => 'home', 'reason' => 'trending'],
        ]);

        CommerceAttributionEvent::factory()->create([
            'source_type' => AttributionSourceType::Recommendation,
            'request_id' => $requestId,
            'product_id' => $product->id,
            'strategy' => 'trending',
            'placement' => 'home',
            'stage' => AttributionFunnelStage::Converted,
            'units' => 1,
            'revenue_amount' => '120.00',
            'occurred_at' => now(),
            'idempotency_key' => 'converted:test:'.$requestId,
        ]);

        $report = app(AttributionAnalyticsServiceInterface::class)->buildRecommendationReport(['preset' => 'today']);
        $this->assertSame(1, $report['summary']['impressions']);
        $this->assertSame(1, $report['summary']['clicks']);
        $this->assertSame(100.0, $report['summary']['ctr']);
        $this->assertNotEmpty($report['strategies']);

        $campaignReport = app(AttributionAnalyticsServiceInterface::class)->buildCampaignReport(['preset' => 'today']);
        $this->assertSame(0, $campaignReport['summary']['impressions']);
        $this->assertNull($campaignReport['summary']['ctr']);
    }

    public function test_campaign_report_aggregates_json_metadata_campaign_ids(): void
    {
        $campaign = Campaign::factory()->active()->popup()->create([
            'name' => 'Home Banner',
            'cta_type' => CampaignCtaType::Close,
            'frequency_policy' => CampaignFrequencyPolicy::EverySession,
            'status' => CampaignStatus::Active,
            'surface' => CampaignSurface::Banner,
            'trigger_rules' => [
                'type' => CampaignTriggerType::Immediate->value,
                'delay_ms' => null,
                'scroll_percent' => null,
                'product_view_count' => null,
            ],
        ]);

        CustomerBehaviourEvent::query()->create([
            'event_type' => BehaviourEventType::CampaignImpression->value,
            'source' => BehaviourEventSource::Client->value,
            'visitor_key' => 'guest'.Str::lower(Str::random(8)),
            'occurred_at' => now(),
            'metadata' => [
                'campaign_id' => $campaign->id,
                'request_id' => (string) Str::uuid(),
                'placement' => 'home',
                'surface' => 'banner',
            ],
        ]);
        CustomerBehaviourEvent::query()->create([
            'event_type' => BehaviourEventType::CampaignClicked->value,
            'source' => BehaviourEventSource::Client->value,
            'visitor_key' => 'guest'.Str::lower(Str::random(8)),
            'occurred_at' => now(),
            'metadata' => [
                'campaign_id' => $campaign->id,
                'request_id' => (string) Str::uuid(),
                'placement' => 'home',
                'surface' => 'banner',
            ],
        ]);

        $report = app(AttributionAnalyticsServiceInterface::class)->buildCampaignReport(['preset' => 'today']);

        $this->assertSame(1, $report['summary']['impressions']);
        $this->assertSame(1, $report['summary']['clicks']);
        $this->assertSame(100.0, $report['summary']['ctr']);
        $this->assertSame($campaign->id, $report['campaigns'][0]['campaign_id'] ?? null);
        $this->assertSame('Home Banner', $report['campaigns'][0]['campaign_name'] ?? null);
    }

    public function test_client_cannot_submit_conversion_events(): void
    {
        $this->postJson(route('api.v1.behaviour.events.store'), [
            'event_type' => BehaviourEventType::CampaignConverted->value,
            'visitor_key' => 'guest'.Str::lower(Str::random(8)),
            'metadata' => ['campaign_id' => 1, 'request_id' => (string) Str::uuid(), 'placement' => 'home'],
        ])->assertStatus(422);

        $this->postJson(route('api.v1.behaviour.events.store'), [
            'event_type' => BehaviourEventType::RecommendationConverted->value,
            'visitor_key' => 'guest'.Str::lower(Str::random(8)),
            'product_id' => 1,
            'metadata' => ['request_id' => (string) Str::uuid(), 'reason' => 'x', 'placement' => 'home'],
        ])->assertStatus(422);
    }

    protected function recordRecClick(
        ?User $customer,
        ?string $visitor,
        Product $product,
        string $requestId,
        string $strategy,
        string $placement,
    ): void {
        CustomerBehaviourEvent::query()->create([
            'event_type' => BehaviourEventType::RecommendationClicked->value,
            'source' => BehaviourEventSource::Client->value,
            'customer_id' => $customer?->id,
            'visitor_key' => $visitor,
            'product_id' => $product->id,
            'occurred_at' => now(),
            'metadata' => [
                'request_id' => $requestId,
                'strategy' => $strategy,
                'reason' => $strategy,
                'placement' => $placement,
            ],
        ]);
    }

    protected function placeAttributedOrder(User $customer, $cartItem): Order
    {
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::Accepted,
            'payment_status' => PaymentStatus::Pending,
            'total_amount' => '150.00',
            'dining_session_id' => null,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $cartItem->productVariant->product_id,
            'product_variant_id' => $cartItem->product_variant_id,
            'product_name' => 'Item',
            'variant_name' => 'Regular',
            'unit_price' => '150.00',
            'quantity' => $cartItem->quantity,
            'line_subtotal' => '150.00',
            'attribution' => app(AttributionServiceInterface::class)->snapshotForOrderItem($cartItem->attribution),
        ]);

        return $order->fresh(['items']);
    }

    protected function makePublicProduct(string $name): Product
    {
        $category = ProductCategory::factory()->create(['is_active' => true]);
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'name' => $name,
            'is_active' => true,
            'is_available' => true,
        ]);
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Regular',
            'price' => 150,
            'is_active' => true,
            'is_available' => true,
        ]);

        return $product->fresh(['variants']);
    }
}
