<?php

namespace Tests\Feature;

use App\Enums\BehaviourEventSource;
use App\Enums\BehaviourEventType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RecommendationContext;
use App\Enums\RecommendationReason;
use App\Models\CustomerBehaviourEvent;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductFavourite;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Personalisation\PersonalisationProfileServiceInterface;
use App\Services\Recommendation\RecommendationAggregateStore;
use App\Services\Recommendation\RecommendationServiceInterface;
use App\Services\Recommendation\Strategies\BuyAgainStrategy;
use App\Services\Recommendation\Strategies\FavouriteStrategy;
use App\Services\Recommendation\Strategies\RepeatedInterestStrategy;
use App\Services\Recommendation\Support\RecommendationQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class RecommendationEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('coffee.behaviour.recommendations.cache_ttl_seconds', 60);
        config()->set('coffee.behaviour.recommendations.trending_min_actors', 2);
        config()->set('coffee.behaviour.recommendations.popular_min_orders', 2);
        config()->set('coffee.behaviour.recommendations.fbt_min_orders', 3);
        config()->set('coffee.behaviour.recommendations.new_arrival_days', 30);
        config()->set('coffee.behaviour.recommendations.max_per_category', 2);
        config()->set('coffee.behaviour.profile.min_evidence_signals', 3);
        config()->set('coffee.behaviour.profile.recency_half_life_days', 3650);

        app(RecommendationAggregateStore::class)->flush();
        Cache::flush();
    }

    public function test_cold_start_guest_recommendations_use_catalog_signals(): void
    {
        $featured = $this->makePublicProduct('Featured Cold', attrs: ['is_featured' => true]);
        $bestseller = $this->makePublicProduct('Bestseller Cold', attrs: ['is_bestseller' => true]);
        $new = $this->makePublicProduct('New Cold', attrs: ['is_new' => true, 'created_at' => now()->subDay()]);

        $response = $this->getJson(route('api.v1.recommendations.index', [
            'context' => RecommendationContext::Home->value,
            'visitor_key' => 'guest'.Str::lower(Str::random(12)),
            'limit' => 8,
        ]));

        $response->assertOk()
            ->assertJsonPath('data.cold_start', true)
            ->assertJsonStructure([
                'data' => [
                    'request_id',
                    'context',
                    'cold_start',
                    'items' => [
                        ['product' => ['id', 'name'], 'reason', 'strategy', 'request_id'],
                    ],
                ],
            ]);

        $ids = collect($response->json('data.items'))->pluck('product.id')->all();
        $this->assertNotEmpty($ids);
        $this->assertTrue(count(array_intersect($ids, [$featured->id, $bestseller->id, $new->id])) > 0);
        $this->assertArrayNotHasKey('base_score', $response->json('data.items.0'));
        $this->assertArrayNotHasKey('final_score', $response->json('data.items.0'));
        $this->assertArrayNotHasKey('product_affinities', $response->json('data'));
    }

    public function test_authenticated_personalized_recommendations_include_buy_again_and_favourites(): void
    {
        config()->set('coffee.behaviour.profile.min_evidence_signals', 1);

        $customer = User::factory()->customer()->create();
        $purchased = $this->makePublicProduct('Buy Again Brew');
        $favourite = $this->makePublicProduct('Fav Brew');
        $this->createCompletedOrder($customer, $purchased, '180.00');
        ProductFavourite::query()->create([
            'customer_id' => $customer->id,
            'product_id' => $favourite->id,
        ]);

        app(PersonalisationProfileServiceInterface::class)->rebuildForCustomer($customer->id);

        $this->actingAs($customer, 'web');

        $response = $this->getJson(route('api.v1.recommendations.index', [
            'context' => RecommendationContext::Home->value,
            'limit' => 10,
        ]));

        $response->assertOk()->assertJsonPath('data.cold_start', false);

        $strategies = collect($response->json('data.items'))->pluck('strategy')->all();
        $reasons = collect($response->json('data.items'))->pluck('reason')->all();
        $ids = collect($response->json('data.items'))->pluck('product.id')->all();

        $this->assertContains('buy_again', $strategies);
        $this->assertContains('favourite', $strategies);
        $this->assertContains(RecommendationReason::BuyAgain->value, $reasons);
        $this->assertContains(RecommendationReason::Favourite->value, $reasons);
        $this->assertContains($purchased->id, $ids);
        $this->assertContains($favourite->id, $ids);
    }

    public function test_anonymous_visitor_repeated_interest_prefers_distinct_days(): void
    {
        $visitor = 'visitor'.Str::lower(Str::random(12));
        $multiDay = $this->makePublicProduct('Multi Day Interest');
        $sameDaySpam = $this->makePublicProduct('Same Day Spam');

        $this->recordEvent(null, $visitor, BehaviourEventType::ProductViewed, $multiDay, occurredAt: now()->subDays(3));
        $this->recordEvent(null, $visitor, BehaviourEventType::ProductViewed, $multiDay, occurredAt: now()->subDay());
        $this->recordEvent(null, $visitor, BehaviourEventType::CartItemAdded, $multiDay, occurredAt: now()->subHours(2));

        for ($i = 0; $i < 12; $i++) {
            $this->recordEvent(null, $visitor, BehaviourEventType::ProductViewed, $sameDaySpam, occurredAt: now()->subMinutes($i));
        }

        $strategy = app(RepeatedInterestStrategy::class);
        $candidates = $strategy->candidates(new RecommendationQuery(
            context: RecommendationContext::Home,
            visitorKey: $visitor,
            limit: 10,
        ));

        $byId = collect($candidates)->keyBy(fn ($c) => $c->productId);
        $this->assertTrue($byId->has($multiDay->id));
        $this->assertFalse($byId->has($sameDaySpam->id));
        $this->assertTrue($byId[$multiDay->id]->baseScore > 4.0);
    }

    public function test_buy_again_excludes_cancelled_orders(): void
    {
        $customer = User::factory()->customer()->create();
        $completed = $this->makePublicProduct('Completed Only');
        $cancelled = $this->makePublicProduct('Cancelled Only');

        $this->createCompletedOrder($customer, $completed, '100.00');
        $this->createOrderWithStatus($customer, $cancelled, OrderStatus::Cancelled, '100.00');

        $candidates = app(BuyAgainStrategy::class)->candidates(new RecommendationQuery(
            context: RecommendationContext::Home,
            customer: $customer,
            limit: 10,
        ));

        $ids = array_map(fn ($c) => $c->productId, $candidates);
        $this->assertSame([$completed->id], $ids);
    }

    public function test_favourite_strategy_returns_favourited_products(): void
    {
        $customer = User::factory()->customer()->create();
        $product = $this->makePublicProduct('Listed Favourite');
        ProductFavourite::query()->create([
            'customer_id' => $customer->id,
            'product_id' => $product->id,
        ]);

        $candidates = app(FavouriteStrategy::class)->candidates(new RecommendationQuery(
            context: RecommendationContext::Home,
            customer: $customer,
            limit: 10,
        ));

        $this->assertCount(1, $candidates);
        $this->assertSame($product->id, $candidates[0]->productId);
        $this->assertSame(RecommendationReason::Favourite, $candidates[0]->reason);
    }

    public function test_affinity_uses_profile_payload_not_raw_rebuild_in_api(): void
    {
        config()->set('coffee.behaviour.profile.min_evidence_signals', 1);

        $customer = User::factory()->customer()->create();
        $category = ProductCategory::factory()->create(['is_active' => true]);
        $affinityProduct = $this->makePublicProduct('Affinity Hit', $category);
        $other = $this->makePublicProduct('Other Cat', ProductCategory::factory()->create(['is_active' => true]));

        $this->createCompletedOrder($customer, $affinityProduct, '200.00');
        app(PersonalisationProfileServiceInterface::class)->rebuildForCustomer($customer->id);

        $this->actingAs($customer, 'web');

        $response = $this->getJson(route('api.v1.recommendations.index', [
            'context' => RecommendationContext::Home->value,
            'limit' => 12,
        ]))->assertOk();

        $ids = collect($response->json('data.items'))->pluck('product.id')->all();
        $this->assertContains($affinityProduct->id, $ids);
        $this->assertNotContains('profile', array_keys($response->json('data')));
        unset($other);
    }

    public function test_similar_products_share_category_context(): void
    {
        $category = ProductCategory::factory()->create(['is_active' => true]);
        $seed = $this->makePublicProduct('Seed Latte', $category);
        $sibling = $this->makePublicProduct('Sibling Latte', $category);
        $other = $this->makePublicProduct('Other Drink', ProductCategory::factory()->create(['is_active' => true]));

        config()->set('coffee.behaviour.recommendations.context_strategies.product_detail', [
            'similar',
        ]);

        $response = $this->getJson(route('api.v1.recommendations.index', [
            'context' => RecommendationContext::ProductDetail->value,
            'product_id' => $seed->id,
            'category_id' => $category->id,
            'visitor_key' => 'guest'.Str::lower(Str::random(10)),
            'limit' => 8,
        ]))->assertOk();

        $ids = collect($response->json('data.items'))->pluck('product.id')->all();
        $this->assertContains($sibling->id, $ids);
        $this->assertNotContains($seed->id, $ids);
        $this->assertNotContains($other->id, $ids);
    }

    public function test_trending_requires_multiple_actors_and_recent_window(): void
    {
        $recent = $this->makePublicProduct('Recent Trend');
        $spam = $this->makePublicProduct('Spam Trend');
        $stale = $this->makePublicProduct('Stale Trend');

        $this->recordEvent(null, 'actor_a', BehaviourEventType::ProductViewed, $recent, occurredAt: now()->subDay());
        $this->recordEvent(null, 'actor_b', BehaviourEventType::CartItemAdded, $recent, occurredAt: now()->subHours(5));

        for ($i = 0; $i < 20; $i++) {
            $this->recordEvent(null, 'solo_spammer', BehaviourEventType::ProductViewed, $spam, occurredAt: now()->subMinutes($i));
        }

        $this->recordEvent(null, 'actor_c', BehaviourEventType::ProductViewed, $stale, occurredAt: now()->subDays(40));
        $this->recordEvent(null, 'actor_d', BehaviourEventType::ProductViewed, $stale, occurredAt: now()->subDays(41));

        app(RecommendationAggregateStore::class)->flush();
        $trending = app(RecommendationAggregateStore::class)->trendingProducts();
        $ids = array_column($trending, 'product_id');

        $this->assertContains($recent->id, $ids);
        $this->assertNotContains($spam->id, $ids);
        $this->assertNotContains($stale->id, $ids);
    }

    public function test_popular_uses_completed_orders_not_cancelled(): void
    {
        $popular = $this->makePublicProduct('Popular Drink');
        $cancelledOnly = $this->makePublicProduct('Never Popular');

        $buyerA = User::factory()->customer()->create();
        $buyerB = User::factory()->customer()->create();
        $this->createCompletedOrder($buyerA, $popular, '120.00');
        $this->createCompletedOrder($buyerB, $popular, '120.00');
        $this->createOrderWithStatus($buyerA, $cancelledOnly, OrderStatus::Cancelled, '120.00');
        $this->createOrderWithStatus($buyerB, $cancelledOnly, OrderStatus::Cancelled, '120.00');

        app(RecommendationAggregateStore::class)->flush();
        $popularRows = app(RecommendationAggregateStore::class)->popularProducts();
        $ids = array_column($popularRows, 'product_id');

        $this->assertContains($popular->id, $ids);
        $this->assertNotContains($cancelledOnly->id, $ids);
    }

    public function test_new_arrival_window_excludes_old_products_without_override(): void
    {
        $fresh = $this->makePublicProduct('Fresh Arrival', attrs: ['created_at' => now()->subDays(2), 'is_new' => false]);
        $old = $this->makePublicProduct('Old Menu Item', attrs: ['created_at' => now()->subDays(120), 'is_new' => false]);
        $override = $this->makePublicProduct('Merch New Flag', attrs: ['created_at' => now()->subDays(120), 'is_new' => true]);

        app(RecommendationAggregateStore::class)->flush();
        $ids = app(RecommendationAggregateStore::class)->newArrivalProductIds();

        $this->assertContains($fresh->id, $ids);
        $this->assertNotContains($old->id, $ids);
        $this->assertContains($override->id, $ids);
    }

    public function test_featured_and_bestseller_merchandising_candidates(): void
    {
        $featured = $this->makePublicProduct('Featured Merch', attrs: ['is_featured' => true]);
        $bestseller = $this->makePublicProduct('Bestseller Merch', attrs: ['is_bestseller' => true]);

        $response = $this->getJson(route('api.v1.recommendations.index', [
            'context' => RecommendationContext::Home->value,
            'visitor_key' => 'guest'.Str::lower(Str::random(10)),
            'limit' => 10,
        ]))->assertOk();

        $ids = collect($response->json('data.items'))->pluck('product.id')->all();
        $this->assertTrue(count(array_intersect($ids, [$featured->id, $bestseller->id])) > 0);
    }

    public function test_frequently_bought_together_respects_minimum_distinct_orders(): void
    {
        $a = $this->makePublicProduct('Pair A');
        $b = $this->makePublicProduct('Pair B');
        $weak = $this->makePublicProduct('Weak Pair');

        for ($i = 0; $i < 3; $i++) {
            $customer = User::factory()->customer()->create();
            $this->createCompletedOrderWithProducts($customer, [$a, $b]);
        }

        $lonely = User::factory()->customer()->create();
        $this->createCompletedOrderWithProducts($lonely, [$a, $weak]);

        app(RecommendationAggregateStore::class)->flush();
        $map = app(RecommendationAggregateStore::class)->frequentlyBoughtTogetherMap();

        $relatedToA = collect($map[$a->id] ?? [])->pluck('product_id')->all();
        $this->assertContains($b->id, $relatedToA);
        $this->assertNotContains($weak->id, $relatedToA);
    }

    public function test_cart_context_recommends_complements_not_in_cart(): void
    {
        $a = $this->makePublicProduct('Cart Seed');
        $b = $this->makePublicProduct('Cart Complement');

        for ($i = 0; $i < 3; $i++) {
            $this->createCompletedOrderWithProducts(User::factory()->customer()->create(), [$a, $b]);
        }

        app(RecommendationAggregateStore::class)->flush();

        $response = $this->getJson(route('api.v1.recommendations.index', [
            'context' => RecommendationContext::Cart->value,
            'visitor_key' => 'guest'.Str::lower(Str::random(10)),
            'cart_product_ids' => [$a->id],
            'limit' => 6,
        ]))->assertOk();

        $ids = collect($response->json('data.items'))->pluck('product.id')->all();
        $this->assertContains($b->id, $ids);
        $this->assertNotContains($a->id, $ids);
    }

    public function test_inactive_and_unavailable_products_are_excluded(): void
    {
        $active = $this->makePublicProduct('Visible', attrs: ['is_featured' => true]);
        $inactive = $this->makePublicProduct('Hidden Inactive', attrs: ['is_featured' => true, 'is_active' => false]);
        $unavailable = $this->makePublicProduct('Hidden Unavailable', attrs: ['is_featured' => true, 'is_available' => false]);

        $response = $this->getJson(route('api.v1.recommendations.index', [
            'context' => RecommendationContext::Home->value,
            'visitor_key' => 'guest'.Str::lower(Str::random(10)),
            'limit' => 10,
        ]))->assertOk();

        $ids = collect($response->json('data.items'))->pluck('product.id')->all();
        $this->assertContains($active->id, $ids);
        $this->assertNotContains($inactive->id, $ids);
        $this->assertNotContains($unavailable->id, $ids);
    }

    public function test_deduplication_and_category_diversity(): void
    {
        $category = ProductCategory::factory()->create(['is_active' => true]);
        $other = ProductCategory::factory()->create(['is_active' => true]);

        $sameCat = [];
        for ($i = 0; $i < 5; $i++) {
            $sameCat[] = $this->makePublicProduct("Same Cat {$i}", $category, ['is_featured' => true]);
        }
        $otherProduct = $this->makePublicProduct('Other Cat Hit', $other, ['is_bestseller' => true]);

        config()->set('coffee.behaviour.recommendations.max_per_category', 2);
        config()->set('coffee.behaviour.recommendations.context_strategies.home', [
            'featured',
            'bestseller',
        ]);

        $payload = app(RecommendationServiceInterface::class)->recommend([
            'context' => RecommendationContext::Home->value,
            'visitor_key' => 'guest'.Str::lower(Str::random(10)),
            'limit' => 8,
        ]);

        $productIds = array_map(fn (array $item): int => (int) $item['product']['id'], $payload['items']);
        $this->assertSame($productIds, array_values(array_unique($productIds)));

        $sameCatCount = count(array_intersect($productIds, array_map(fn ($p) => $p->id, $sameCat)));
        $this->assertLessThanOrEqual(2, $sameCatCount);
        $this->assertContains($otherProduct->id, $productIds);
    }

    public function test_ranking_is_deterministic_for_same_inputs(): void
    {
        $this->makePublicProduct('Det A', attrs: ['is_featured' => true]);
        $this->makePublicProduct('Det B', attrs: ['is_bestseller' => true]);
        $this->makePublicProduct('Det C', attrs: ['is_new' => true]);

        $input = [
            'context' => RecommendationContext::Home->value,
            'visitor_key' => 'guest_det_'.Str::lower(Str::random(8)),
            'limit' => 8,
        ];

        $first = app(RecommendationServiceInterface::class)->recommend($input);
        $second = app(RecommendationServiceInterface::class)->recommend($input);

        $this->assertSame(
            array_map(fn ($i) => [$i['product']['id'], $i['reason'], $i['strategy']], $first['items']),
            array_map(fn ($i) => [$i['product']['id'], $i['reason'], $i['strategy']], $second['items']),
        );
        $this->assertNotSame($first['request_id'], $second['request_id']);
    }

    public function test_guest_and_customer_recommendations_are_isolated(): void
    {
        config()->set('coffee.behaviour.profile.min_evidence_signals', 1);

        $customerA = User::factory()->customer()->create();
        $customerB = User::factory()->customer()->create();
        $aOnly = $this->makePublicProduct('A Private Purchase');
        $bOnly = $this->makePublicProduct('B Private Purchase');

        $this->createCompletedOrder($customerA, $aOnly, '150.00');
        $this->createCompletedOrder($customerB, $bOnly, '150.00');
        app(PersonalisationProfileServiceInterface::class)->rebuildForCustomer($customerA->id);
        app(PersonalisationProfileServiceInterface::class)->rebuildForCustomer($customerB->id);

        $forA = app(RecommendationServiceInterface::class)->recommend([
            'context' => RecommendationContext::Home->value,
            'limit' => 10,
        ], $customerA);

        $forB = app(RecommendationServiceInterface::class)->recommend([
            'context' => RecommendationContext::Home->value,
            'limit' => 10,
        ], $customerB);

        $buyAgainA = collect($forA['items'])->where('strategy', 'buy_again')->pluck('product.id')->all();
        $buyAgainB = collect($forB['items'])->where('strategy', 'buy_again')->pluck('product.id')->all();

        $this->assertContains($aOnly->id, $buyAgainA);
        $this->assertNotContains($bOnly->id, $buyAgainA);
        $this->assertContains($bOnly->id, $buyAgainB);
        $this->assertNotContains($aOnly->id, $buyAgainB);
    }

    public function test_aggregate_cache_hit_and_miss_share_shape(): void
    {
        $product = $this->makePublicProduct('Cached Popular');
        $buyerA = User::factory()->customer()->create();
        $buyerB = User::factory()->customer()->create();
        $this->createCompletedOrder($buyerA, $product, '110.00');
        $this->createCompletedOrder($buyerB, $product, '110.00');

        $store = app(RecommendationAggregateStore::class);
        $store->flush();

        $miss = $store->popularProducts();
        $hit = $store->popularProducts();

        $this->assertSame($miss, $hit);
        $this->assertIsArray($hit);
        $this->assertArrayHasKey('product_id', $hit[0]);
        $this->assertArrayHasKey('order_count', $hit[0]);
        $this->assertArrayHasKey('quantity', $hit[0]);
        $this->assertSame(array_keys($miss[0]), array_keys($hit[0]));
    }

    public function test_recommendation_impression_and_click_validation(): void
    {
        $product = $this->makePublicProduct('Tracked Rec');
        $visitor = 'guest'.Str::lower(Str::random(12));
        $requestId = (string) Str::uuid();

        $this->postJson(route('api.v1.behaviour.events.store'), [
            'event_type' => BehaviourEventType::RecommendationImpression->value,
            'visitor_key' => $visitor,
            'product_id' => $product->id,
            'metadata' => [
                'request_id' => $requestId,
                'reason' => RecommendationReason::Trending->value,
                'strategy' => 'trending',
                'placement' => 'home_rail',
                'context' => 'home',
            ],
        ])->assertOk();

        $this->postJson(route('api.v1.behaviour.events.store'), [
            'event_type' => BehaviourEventType::RecommendationClicked->value,
            'visitor_key' => $visitor,
            'product_id' => $product->id,
            'metadata' => [
                'request_id' => $requestId,
                'reason' => RecommendationReason::Trending->value,
                'strategy' => 'trending',
                'placement' => 'home_rail',
            ],
        ])->assertOk();

        $this->postJson(route('api.v1.behaviour.events.store'), [
            'event_type' => BehaviourEventType::RecommendationImpression->value,
            'visitor_key' => $visitor,
            'product_id' => $product->id,
            'metadata' => [
                'reason' => RecommendationReason::Trending->value,
            ],
        ])->assertStatus(422);

        $this->postJson(route('api.v1.behaviour.events.store'), [
            'event_type' => BehaviourEventType::RecommendationClicked->value,
            'visitor_key' => $visitor,
            'metadata' => [
                'request_id' => $requestId,
                'reason' => RecommendationReason::Trending->value,
                'placement' => 'home_rail',
            ],
        ])->assertStatus(422);
    }

    public function test_api_privacy_omits_scores_and_sensitive_fields(): void
    {
        $this->makePublicProduct('Privacy Featured', attrs: ['is_featured' => true]);

        $json = $this->getJson(route('api.v1.recommendations.index', [
            'context' => RecommendationContext::Home->value,
            'visitor_key' => 'guest'.Str::lower(Str::random(10)),
        ]))->assertOk()->json('data');

        $encoded = json_encode($json);
        $this->assertStringNotContainsString('base_score', $encoded);
        $this->assertStringNotContainsString('final_score', $encoded);
        $this->assertStringNotContainsString('product_affinities', $encoded);
        $this->assertStringNotContainsString('has_sufficient_evidence', $encoded);
        $this->assertStringNotContainsString('recipe', $encoded);
        $this->assertStringNotContainsString('cost', $encoded);
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    protected function makePublicProduct(string $name, ?ProductCategory $category = null, array $attrs = []): Product
    {
        $category ??= ProductCategory::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(array_merge([
            'product_category_id' => $category->id,
            'name' => $name,
            'is_active' => true,
            'is_available' => true,
            'is_featured' => false,
            'is_bestseller' => false,
            'is_new' => false,
        ], $attrs));

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Regular',
            'price' => 120,
            'is_active' => true,
            'is_available' => true,
        ]);

        return $product->fresh(['category', 'variants']);
    }

    protected function recordEvent(
        ?User $customer,
        ?string $visitorKey,
        BehaviourEventType $type,
        ?Product $product = null,
        ?Carbon $occurredAt = null,
    ): CustomerBehaviourEvent {
        return CustomerBehaviourEvent::query()->create([
            'event_type' => $type->value,
            'source' => BehaviourEventSource::Client->value,
            'customer_id' => $customer?->id,
            'visitor_key' => $visitorKey ?? 'test'.Str::lower(Str::random(8)),
            'product_id' => $product?->id,
            'product_category_id' => $product?->product_category_id,
            'occurred_at' => $occurredAt ?? now(),
        ]);
    }

    protected function createCompletedOrder(User $customer, Product $product, string $total): Order
    {
        return $this->createOrderWithStatus($customer, $product, OrderStatus::Completed, $total);
    }

    protected function createOrderWithStatus(
        User $customer,
        Product $product,
        OrderStatus $status,
        string $total,
    ): Order {
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => $status,
            'payment_status' => $status === OrderStatus::Completed
                ? PaymentStatus::Confirmed
                : PaymentStatus::Pending,
            'total_amount' => $total,
            'completed_at' => $status === OrderStatus::Completed ? now() : null,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $product->variants()->first()?->id,
            'product_name' => $product->name,
            'variant_name' => 'Regular',
            'unit_price' => $total,
            'quantity' => 1,
            'line_subtotal' => $total,
        ]);

        return $order->fresh(['items']);
    }

    /**
     * @param  list<Product>  $products
     */
    protected function createCompletedOrderWithProducts(User $customer, array $products): Order
    {
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::Completed,
            'payment_status' => PaymentStatus::Confirmed,
            'total_amount' => '300.00',
            'completed_at' => now(),
        ]);

        foreach ($products as $product) {
            OrderItem::factory()->create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_variant_id' => $product->variants()->first()?->id,
                'product_name' => $product->name,
                'variant_name' => 'Regular',
                'unit_price' => '100.00',
                'quantity' => 1,
                'line_subtotal' => '100.00',
            ]);
        }

        return $order->fresh(['items']);
    }
}
