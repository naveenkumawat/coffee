<?php

namespace Tests\Feature;

use App\Enums\AudienceSegmentActor;
use App\Enums\AudienceSegmentStatus;
use App\Enums\BehaviourEventSource;
use App\Enums\BehaviourEventType;
use App\Enums\CampaignCtaType;
use App\Enums\CampaignFrequencyPolicy;
use App\Enums\CampaignStatus;
use App\Enums\CampaignSurface;
use App\Enums\CampaignTriggerType;
use App\Enums\HomeSectionPlacement;
use App\Enums\HomeSectionSourceType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductServingUnit;
use App\Enums\RecommendationContext;
use App\Models\AudienceSegment;
use App\Models\Campaign;
use App\Models\CustomerBehaviourEvent;
use App\Models\HomeSection;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductFavourite;
use App\Models\ProductTag;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Merchandising\MerchandisingServiceInterface;
use App\Services\Personalisation\PersonalisationProfileServiceInterface;
use App\Services\Recommendation\RecommendationAggregateStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class MerchandisingLandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('coffee.behaviour.merchandising.config_cache_ttl_seconds', 30);
        config()->set('coffee.behaviour.recommendations.cache_ttl_seconds', 30);
        config()->set('coffee.behaviour.campaigns.cache_ttl_seconds', 30);
        config()->set('coffee.behaviour.profile.min_evidence_signals', 3);
        config()->set('coffee.behaviour.enabled', true);

        app(RecommendationAggregateStore::class)->flush();
        app(MerchandisingServiceInterface::class)->flushConfigCache();
        Cache::flush();
    }

    public function test_cold_start_home_returns_curated_sections(): void
    {
        $product = $this->makePublicProduct('Featured Curated');
        $section = $this->makeCuratedSection('Featured', [$product], ['sort_order' => 10, 'priority' => 10]);

        $response = $this->getJson(route('api.v1.home.show'))
            ->assertOk()
            ->assertJsonPath('data.placement', 'home')
            ->assertJsonPath('data.sections.0.id', $section->id)
            ->assertJsonPath('data.sections.0.source_type', 'curated')
            ->assertJsonPath('data.sections.0.products.0.name', 'Featured Curated')
            ->assertJsonPath('data.campaigns.banner', null)
            ->assertJsonPath('data.campaigns.inline', null);

        $this->assertArrayNotHasKey('targeting_rules', $response->json('data.sections.0'));
        $this->assertArrayNotHasKey('profile', $response->json('data'));
    }

    public function test_tracking_disabled_still_returns_generic_curated_home(): void
    {
        config()->set('coffee.behaviour.enabled', false);

        $product = $this->makePublicProduct('Safe Curated');
        $this->makeCuratedSection('Always On', [$product]);

        $this->getJson(route('api.v1.home.show', [
            'visitor_key' => 'guest'.Str::lower(Str::random(10)),
        ]))
            ->assertOk()
            ->assertJsonPath('data.sections.0.products.0.name', 'Safe Curated');
    }

    public function test_schedule_and_inactive_sections_are_filtered(): void
    {
        $product = $this->makePublicProduct('Live Product');
        $this->makeCuratedSection('Live', [$product], [
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
        ]);
        $this->makeCuratedSection('Future', [$product], [
            'title' => 'Future',
            'slug' => 'future',
            'starts_at' => now()->addDay(),
        ]);
        $this->makeCuratedSection('Expired', [$product], [
            'title' => 'Expired',
            'slug' => 'expired',
            'ends_at' => now()->subHour(),
        ]);
        HomeSection::factory()->inactive()->create([
            'title' => 'Inactive',
            'slug' => 'inactive',
        ])->products()->attach($product->id, ['sort_order' => 10]);

        $titles = collect($this->getJson(route('api.v1.home.show'))->json('data.sections'))->pluck('title')->all();

        $this->assertSame(['Live'], $titles);
    }

    public function test_priority_and_sort_order_are_deterministic(): void
    {
        $a = $this->makePublicProduct('A');
        $b = $this->makePublicProduct('B');
        $c = $this->makePublicProduct('C');

        $this->makeCuratedSection('Low Priority', [$a], ['priority' => 1, 'sort_order' => 1, 'slug' => 'low']);
        $this->makeCuratedSection('High Priority', [$b], ['priority' => 50, 'sort_order' => 99, 'slug' => 'high']);
        $this->makeCuratedSection('Same Priority Later', [$c], ['priority' => 50, 'sort_order' => 5, 'slug' => 'same-later']);

        $titles = collect($this->getJson(route('api.v1.home.show'))->json('data.sections'))->pluck('title')->all();

        $this->assertSame(['Same Priority Later', 'High Priority', 'Low Priority'], $titles);
    }

    public function test_cross_section_dedupe_and_intentional_duplicates(): void
    {
        $shared = $this->makePublicProduct('Shared Drink');
        $unique = $this->makePublicProduct('Unique Drink');

        $this->makeCuratedSection('First', [$shared, $unique], [
            'priority' => 20,
            'dedupe_products' => true,
            'slug' => 'first',
        ]);
        $this->makeCuratedSection('Deduped', [$shared], [
            'priority' => 10,
            'dedupe_products' => true,
            'slug' => 'deduped',
            'title' => 'Deduped',
        ]);
        $this->makeCuratedSection('Allowed Dup', [$shared], [
            'priority' => 5,
            'dedupe_products' => false,
            'slug' => 'allowed-dup',
            'title' => 'Allowed Dup',
        ]);

        $sections = $this->getJson(route('api.v1.home.show'))->json('data.sections');
        $byTitle = collect($sections)->keyBy('title');

        $this->assertCount(2, $byTitle['First']['products']);
        $this->assertArrayNotHasKey('Deduped', $byTitle->all());
        $this->assertSame([$shared->id], collect($byTitle['Allowed Dup']['products'])->pluck('id')->all());
    }

    public function test_segment_targeted_section_and_inactive_segment_fail_safe(): void
    {
        $everyoneProduct = $this->makePublicProduct('Everyone Drink');
        $segmentProduct = $this->makePublicProduct('Segment Drink');
        $visitor = 'visitor'.Str::lower(Str::random(10));

        CustomerBehaviourEvent::query()->create([
            'event_type' => BehaviourEventType::ProductViewed->value,
            'source' => BehaviourEventSource::Client->value,
            'visitor_key' => $visitor,
            'product_id' => $segmentProduct->id,
            'product_category_id' => $segmentProduct->product_category_id,
            'occurred_at' => now()->subDay(),
        ]);
        CustomerBehaviourEvent::query()->create([
            'event_type' => BehaviourEventType::CategoryViewed->value,
            'source' => BehaviourEventSource::Client->value,
            'visitor_key' => $visitor,
            'product_category_id' => $segmentProduct->product_category_id,
            'occurred_at' => now(),
        ]);

        $activeSegment = AudienceSegment::factory()->create([
            'name' => 'Returning Guests',
            'status' => AudienceSegmentStatus::Active,
            'actor_scope' => AudienceSegmentActor::Visitor,
            'rules' => [
                'all' => [['type' => 'returning_visitor', 'op' => 'eq', 'value' => true]],
                'any' => [],
                'exclude' => [],
            ],
        ]);
        $inactiveSegment = AudienceSegment::factory()->create([
            'name' => 'Paused',
            'status' => AudienceSegmentStatus::Paused,
            'actor_scope' => AudienceSegmentActor::Visitor,
            'rules' => [
                'all' => [['type' => 'identity', 'op' => 'eq', 'value' => 'guest']],
                'any' => [],
                'exclude' => [],
            ],
        ]);

        $this->makeCuratedSection('Everyone', [$everyoneProduct], ['priority' => 1, 'slug' => 'everyone', 'title' => 'Everyone']);
        $this->makeCuratedSection('Returning Only', [$segmentProduct], [
            'priority' => 20,
            'slug' => 'returning-only',
            'title' => 'Returning Only',
            'targeting_rules' => [
                'all' => [['type' => 'segment_matches', 'op' => 'eq', 'value' => $activeSegment->id]],
                'any' => [],
                'exclude' => [],
            ],
        ]);
        $this->makeCuratedSection('Paused Segment', [$segmentProduct], [
            'priority' => 30,
            'slug' => 'paused-segment',
            'title' => 'Paused Segment',
            'targeting_rules' => [
                'all' => [['type' => 'segment_matches', 'op' => 'eq', 'value' => $inactiveSegment->id]],
                'any' => [],
                'exclude' => [],
            ],
        ]);

        $newVisitorTitles = collect($this->getJson(route('api.v1.home.show', [
            'visitor_key' => 'new'.Str::lower(Str::random(10)),
        ]))->json('data.sections'))->pluck('title')->all();

        $this->assertSame(['Everyone'], $newVisitorTitles);

        $returningTitles = collect($this->getJson(route('api.v1.home.show', [
            'visitor_key' => $visitor,
        ]))->json('data.sections'))->pluck('title')->all();

        $this->assertSame(['Returning Only', 'Everyone'], $returningTitles);
    }

    public function test_recommendation_backed_and_buy_again_sections_preserve_attribution(): void
    {
        config()->set('coffee.behaviour.profile.min_evidence_signals', 1);

        $customer = User::factory()->customer()->create();
        $purchased = $this->makePublicProduct('Buy Again Brew');
        $featured = $this->makePublicProduct('Featured Cold', attrs: ['is_featured' => true]);
        $this->createCompletedOrder($customer, $purchased, '120.00');
        app(PersonalisationProfileServiceInterface::class)->rebuildForCustomer($customer->id);

        HomeSection::factory()->create([
            'title' => 'Buy Again Rail',
            'slug' => 'buy-again-rail',
            'source_type' => HomeSectionSourceType::BuyAgain,
            'recommendation_context' => RecommendationContext::Home->value,
            'priority' => 40,
            'is_active' => true,
            'max_items' => 4,
            'fallback_to_curated' => false,
        ]);
        HomeSection::factory()->create([
            'title' => 'Rec Rail',
            'slug' => 'rec-rail',
            'source_type' => HomeSectionSourceType::Recommendation,
            'recommendation_context' => RecommendationContext::Home->value,
            'priority' => 10,
            'is_active' => true,
            'max_items' => 6,
            'fallback_to_curated' => true,
        ])->products()->attach($featured->id, ['sort_order' => 10]);

        $this->actingAs($customer, 'web');

        $response = $this->getJson(route('api.v1.home.show'))->assertOk();
        $sections = collect($response->json('data.sections'));

        $buyAgain = $sections->firstWhere('title', 'Buy Again Rail');
        $this->assertNotNull($buyAgain);
        $this->assertNotEmpty($buyAgain['recommendation']['request_id'] ?? null);
        $this->assertContains($purchased->id, collect($buyAgain['products'])->pluck('id')->all());
        $this->assertSame('recommendation', $buyAgain['products'][0]['attribution']['source_type'] ?? null);
        $this->assertSame($buyAgain['recommendation']['request_id'], $buyAgain['products'][0]['attribution']['request_id'] ?? null);

        $this->assertArrayNotHasKey('final_score', $buyAgain['products'][0]);
        $this->assertArrayNotHasKey('product_affinities', $response->json('data'));
    }

    public function test_trending_and_featured_generic_fallback_without_evidence(): void
    {
        $featured = $this->makePublicProduct('Featured Cold', attrs: ['is_featured' => true]);
        $this->makePublicProduct('Bestseller Cold', attrs: ['is_bestseller' => true]);
        app(RecommendationAggregateStore::class)->flush();

        HomeSection::factory()->create([
            'title' => 'Featured Rail',
            'slug' => 'featured-rail',
            'source_type' => HomeSectionSourceType::Featured,
            'recommendation_context' => RecommendationContext::Home->value,
            'priority' => 20,
            'is_active' => true,
            'max_items' => 4,
            'fallback_to_curated' => false,
        ]);
        app(MerchandisingServiceInterface::class)->flushConfigCache();

        $response = $this->getJson(route('api.v1.home.show', [
            'visitor_key' => 'guest'.Str::lower(Str::random(10)),
        ]))->assertOk();

        $section = collect($response->json('data.sections'))->firstWhere('title', 'Featured Rail');
        $this->assertNotNull($section);
        $this->assertContains($featured->id, collect($section['products'])->pluck('id')->all());
        $this->assertTrue((bool) ($section['recommendation']['cold_start'] ?? false));
    }

    public function test_empty_recommendation_evidence_falls_back_to_curated(): void
    {
        $curated = $this->makePublicProduct('Curated Fallback');

        $section = HomeSection::factory()->create([
            'title' => 'Warm Prefer',
            'slug' => 'warm-prefer',
            'source_type' => HomeSectionSourceType::BuyAgain,
            'priority' => 10,
            'is_active' => true,
            'fallback_to_curated' => true,
            'max_items' => 3,
        ]);
        $section->products()->attach($curated->id, ['sort_order' => 10]);

        $response = $this->getJson(route('api.v1.home.show', [
            'visitor_key' => 'guest'.Str::lower(Str::random(10)),
        ]))->assertOk();

        $payload = collect($response->json('data.sections'))->firstWhere('title', 'Warm Prefer');
        $this->assertNotNull($payload);
        $this->assertSame([$curated->id], collect($payload['products'])->pluck('id')->all());
        $this->assertNull($payload['recommendation']);
    }

    public function test_menu_placement_and_category_source(): void
    {
        $category = ProductCategory::factory()->create(['is_active' => true]);
        $inCategory = $this->makePublicProduct('Cat Drink', $category);
        $this->makePublicProduct('Other Drink');

        HomeSection::factory()->forMenu()->create([
            'title' => 'Category Rail',
            'slug' => 'category-rail',
            'source_type' => HomeSectionSourceType::Category,
            'source_category_id' => $category->id,
            'priority' => 10,
            'is_active' => true,
            'max_items' => 5,
        ]);

        $home = $this->getJson(route('api.v1.home.show'))->assertOk()->json('data.sections');
        $this->assertSame([], $home);

        $menu = $this->getJson(route('api.v1.home.show', ['placement' => 'menu']))
            ->assertOk()
            ->assertJsonPath('data.placement', 'menu')
            ->json('data.sections');

        $this->assertCount(1, $menu);
        $this->assertSame([$inCategory->id], collect($menu[0]['products'])->pluck('id')->all());
    }

    public function test_campaign_banner_and_inline_integration_preserves_attribution(): void
    {
        $product = $this->makePublicProduct('Banner Drink');
        $this->makeCuratedSection('Base', [$product]);

        $banner = Campaign::factory()->active()->create([
            'name' => 'Home Banner',
            'surface' => CampaignSurface::Banner,
            'title' => 'Banner Title',
            'cta_type' => CampaignCtaType::Product,
            'cta_product_id' => $product->id,
            'cta_label' => 'Order',
            'priority' => 20,
            'placement_rules' => [
                'placements' => ['home'],
                'category_ids' => [],
                'product_ids' => [],
                'product_tag_ids' => [],
            ],
            'targeting_rules' => [
                'all' => [['type' => 'identity', 'op' => 'eq', 'value' => 'everyone']],
                'any' => [],
                'exclude' => [],
            ],
            'trigger_rules' => [
                'type' => CampaignTriggerType::Immediate->value,
                'delay_ms' => null,
                'scroll_percent' => null,
                'product_view_count' => null,
            ],
            'frequency_policy' => CampaignFrequencyPolicy::EverySession,
            'status' => CampaignStatus::Active,
        ]);
        $inline = Campaign::factory()->active()->create([
            'name' => 'Home Inline',
            'surface' => CampaignSurface::Inline,
            'title' => 'Inline Title',
            'cta_type' => CampaignCtaType::Close,
            'priority' => 10,
            'placement_rules' => [
                'placements' => ['home'],
                'category_ids' => [],
                'product_ids' => [],
                'product_tag_ids' => [],
            ],
            'targeting_rules' => [
                'all' => [['type' => 'identity', 'op' => 'eq', 'value' => 'everyone']],
                'any' => [],
                'exclude' => [],
            ],
            'trigger_rules' => [
                'type' => CampaignTriggerType::Immediate->value,
                'delay_ms' => null,
                'scroll_percent' => null,
                'product_view_count' => null,
            ],
            'frequency_policy' => CampaignFrequencyPolicy::EverySession,
            'status' => CampaignStatus::Active,
        ]);

        $response = $this->getJson(route('api.v1.home.show', [
            'visitor_key' => 'guest'.Str::lower(Str::random(10)),
            'session_key' => 'sess'.Str::lower(Str::random(8)),
        ]))->assertOk();

        $this->assertSame($banner->id, $response->json('data.campaigns.banner.id'));
        $this->assertSame($inline->id, $response->json('data.campaigns.inline.id'));
        $this->assertNotEmpty($response->json('data.campaigns.banner.request_id'));
        $this->assertNotEmpty($response->json('data.campaigns.banner.attribution_key'));
        $this->assertArrayNotHasKey('targeting_rules', $response->json('data.campaigns.banner'));
    }

    public function test_authenticated_personalised_home_and_customer_isolation(): void
    {
        config()->set('coffee.behaviour.profile.min_evidence_signals', 1);

        $alice = User::factory()->customer()->create();
        $bob = User::factory()->customer()->create();
        $aliceProduct = $this->makePublicProduct('Alice Brew');
        $bobProduct = $this->makePublicProduct('Bob Brew');

        $this->createCompletedOrder($alice, $aliceProduct, '90.00');
        $this->createCompletedOrder($bob, $bobProduct, '95.00');
        ProductFavourite::query()->create([
            'customer_id' => $alice->id,
            'product_id' => $aliceProduct->id,
        ]);
        ProductFavourite::query()->create([
            'customer_id' => $bob->id,
            'product_id' => $bobProduct->id,
        ]);
        app(PersonalisationProfileServiceInterface::class)->rebuildForCustomer($alice->id);
        app(PersonalisationProfileServiceInterface::class)->rebuildForCustomer($bob->id);

        HomeSection::factory()->create([
            'title' => 'Favourites',
            'slug' => 'favourites',
            'source_type' => HomeSectionSourceType::Favourite,
            'priority' => 30,
            'is_active' => true,
            'fallback_to_curated' => false,
            'max_items' => 4,
        ]);
        app(MerchandisingServiceInterface::class)->flushConfigCache();

        $this->actingAs($alice, 'web');
        $aliceIds = collect($this->getJson(route('api.v1.home.show'))->json('data.sections.0.products'))->pluck('id')->all();
        $this->assertContains($aliceProduct->id, $aliceIds);
        $this->assertNotContains($bobProduct->id, $aliceIds);

        $this->actingAs($bob, 'web');
        $bobIds = collect($this->getJson(route('api.v1.home.show'))->json('data.sections.0.products'))->pluck('id')->all();
        $this->assertContains($bobProduct->id, $bobIds);
        $this->assertNotContains($aliceProduct->id, $bobIds);
    }

    public function test_visitor_to_customer_transition_changes_future_payload(): void
    {
        config()->set('coffee.behaviour.profile.min_evidence_signals', 1);

        $visitor = 'visitor'.Str::lower(Str::random(10));
        $customer = User::factory()->customer()->create();
        $purchased = $this->makePublicProduct('Merged Brew');
        $featured = $this->makePublicProduct('Featured Guest', attrs: ['is_featured' => true]);

        HomeSection::factory()->create([
            'title' => 'Buy Again',
            'slug' => 'buy-again',
            'source_type' => HomeSectionSourceType::BuyAgain,
            'priority' => 40,
            'is_active' => true,
            'fallback_to_curated' => true,
            'max_items' => 4,
        ])->products()->attach($featured->id, ['sort_order' => 10]);

        $guest = $this->getJson(route('api.v1.home.show', ['visitor_key' => $visitor]))->assertOk();
        $guestIds = collect($guest->json('data.sections.0.products'))->pluck('id')->all();
        $this->assertContains($featured->id, $guestIds);

        $this->createCompletedOrder($customer, $purchased, '110.00');
        app(PersonalisationProfileServiceInterface::class)->rebuildForCustomer($customer->id);

        $this->actingAs($customer, 'web');
        $auth = $this->getJson(route('api.v1.home.show', ['visitor_key' => $visitor]))->assertOk();
        $authIds = collect($auth->json('data.sections.0.products'))->pluck('id')->all();
        $this->assertContains($purchased->id, $authIds);
    }

    public function test_api_privacy_excludes_internal_signals(): void
    {
        $product = $this->makePublicProduct('Public Drink');
        $this->makeCuratedSection('Public', [$product]);

        $json = $this->getJson(route('api.v1.home.show'))->assertOk()->json('data');

        $encoded = json_encode($json);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('targeting_rules', $encoded);
        $this->assertStringNotContainsString('product_affinities', $encoded);
        $this->assertStringNotContainsString('final_score', $encoded);
        $this->assertStringNotContainsString('spend_band', $encoded);
        $this->assertStringNotContainsString('recipe', $encoded);
    }

    public function test_tag_source_section(): void
    {
        $tag = ProductTag::factory()->create(['is_active' => true, 'name' => 'Seasonal']);
        $tagged = $this->makePublicProduct('Tagged Drink');
        $tagged->tags()->attach($tag->id);
        $this->makePublicProduct('Untagged');

        HomeSection::factory()->create([
            'title' => 'Tagged',
            'slug' => 'tagged',
            'source_type' => HomeSectionSourceType::Tag,
            'source_tag_id' => $tag->id,
            'is_active' => true,
            'priority' => 5,
            'max_items' => 5,
        ]);

        $ids = collect($this->getJson(route('api.v1.home.show'))->json('data.sections.0.products'))->pluck('id')->all();
        $this->assertSame([$tagged->id], $ids);
    }

    /**
     * @param  list<Product>  $products
     * @param  array<string, mixed>  $attrs
     */
    protected function makeCuratedSection(string $title, array $products, array $attrs = []): HomeSection
    {
        $section = HomeSection::factory()->create(array_merge([
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(4)),
            'source_type' => HomeSectionSourceType::Curated,
            'placement' => HomeSectionPlacement::Home,
            'is_active' => true,
            'priority' => 0,
            'dedupe_products' => true,
            'fallback_to_curated' => true,
        ], $attrs));

        foreach (array_values($products) as $index => $product) {
            $section->products()->attach($product->id, ['sort_order' => ($index + 1) * 10]);
        }

        app(MerchandisingServiceInterface::class)->flushConfigCache();

        return $section->fresh();
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
        ], $attrs));

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Regular',
            'serving_size_value' => '250',
            'serving_size_unit' => ProductServingUnit::Milliliter,
            'price' => '9.50',
            'is_active' => true,
            'is_available' => true,
            'sort_order' => 1,
        ]);

        return $product->fresh(['variants']);
    }

    protected function createCompletedOrder(User $customer, Product $product, string $total): Order
    {
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::Completed,
            'payment_status' => PaymentStatus::Confirmed,
            'total_amount' => $total,
            'completed_at' => now(),
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
}
