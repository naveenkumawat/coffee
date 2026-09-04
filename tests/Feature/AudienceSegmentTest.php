<?php

namespace Tests\Feature;

use App\Enums\AudienceSegmentActor;
use App\Enums\AudienceSegmentStatus;
use App\Enums\BehaviourEventSource;
use App\Enums\BehaviourEventType;
use App\Enums\CampaignCtaType;
use App\Enums\CampaignFrequencyPolicy;
use App\Enums\CampaignPlacement;
use App\Enums\CampaignStatus;
use App\Enums\CampaignSurface;
use App\Enums\CampaignTriggerType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\AudienceSegment;
use App\Models\Campaign;
use App\Models\CustomerBehaviourEvent;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductFavourite;
use App\Models\ProductFlavour;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Campaign\CampaignEligibilityServiceInterface;
use App\Services\Campaign\CampaignRuleValidator;
use App\Services\Personalisation\PersonalisationProfileServiceInterface;
use App\Services\Segment\SegmentServiceInterface;
use App\Services\Targeting\TargetingRuleValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AudienceSegmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('coffee.behaviour.segments.cache_ttl_seconds', 30);
        config()->set('coffee.behaviour.campaigns.cache_ttl_seconds', 30);
        app(SegmentServiceInterface::class)->flushMatchCache();
        app(CampaignEligibilityServiceInterface::class)->flushConfigCache();
    }

    public function test_guest_new_visitor_segment(): void
    {
        $segment = $this->activeSegment('New Visitors', [
            'actor_scope' => AudienceSegmentActor::Visitor,
            'rules' => [
                'all' => [['type' => 'identity', 'op' => 'eq', 'value' => 'new_visitor']],
                'any' => [],
                'exclude' => [],
            ],
        ]);

        $hit = app(SegmentServiceInterface::class)->matches($segment, [
            'visitor_key' => 'guest'.Str::lower(Str::random(8)),
        ]);

        $this->assertTrue($hit['matches']);
    }

    public function test_returning_visitor_segment(): void
    {
        $visitor = 'return'.Str::lower(Str::random(8));
        $product = $this->makePublicProduct('Viewed');

        CustomerBehaviourEvent::query()->create([
            'event_type' => BehaviourEventType::ProductViewed->value,
            'source' => BehaviourEventSource::Client->value,
            'visitor_key' => $visitor,
            'product_id' => $product->id,
            'occurred_at' => now()->subDay(),
        ]);
        CustomerBehaviourEvent::query()->create([
            'event_type' => BehaviourEventType::CategoryViewed->value,
            'source' => BehaviourEventSource::Client->value,
            'visitor_key' => $visitor,
            'product_category_id' => $product->product_category_id,
            'occurred_at' => now(),
        ]);

        $segment = $this->activeSegment('Returning Visitors', [
            'actor_scope' => AudienceSegmentActor::Visitor,
            'rules' => [
                'all' => [['type' => 'returning_visitor', 'op' => 'eq', 'value' => true]],
                'any' => [],
                'exclude' => [],
            ],
        ]);

        $this->assertTrue(app(SegmentServiceInterface::class)->matches($segment, [
            'visitor_key' => $visitor,
        ])['matches']);
    }

    public function test_authenticated_customer_and_new_customer_segments(): void
    {
        $customer = User::factory()->customer()->create();

        $auth = $this->activeSegment('Authenticated', [
            'actor_scope' => AudienceSegmentActor::Customer,
            'rules' => [
                'all' => [['type' => 'identity', 'op' => 'eq', 'value' => 'authenticated']],
                'any' => [],
                'exclude' => [],
            ],
        ]);
        $newCustomer = $this->activeSegment('New Customers', [
            'actor_scope' => AudienceSegmentActor::Customer,
            'rules' => [
                'all' => [['type' => 'identity', 'op' => 'eq', 'value' => 'new_customer']],
                'any' => [],
                'exclude' => [],
            ],
        ]);

        $service = app(SegmentServiceInterface::class);
        $this->assertTrue($service->matches($auth, [], $customer)['matches']);
        $this->assertTrue($service->matches($newCustomer, [], $customer)['matches']);
        $this->assertFalse($service->matches($auth, ['visitor_key' => 'guest_only'])['matches']);
    }

    public function test_repeat_and_frequent_buyer_segments(): void
    {
        config()->set('coffee.behaviour.profile.min_evidence_signals', 1);

        $customer = User::factory()->customer()->create();
        $product = $this->makePublicProduct('Regular');
        $this->createCompletedOrder($customer, $product, now()->subDays(10));
        $this->createCompletedOrder($customer, $product, now()->subDays(5));
        $this->createCompletedOrder($customer, $product, now()->subDays(1));

        app(PersonalisationProfileServiceInterface::class)->rebuildForCustomer($customer->id);

        $repeat = $this->activeSegment('Repeat Buyers', [
            'actor_scope' => AudienceSegmentActor::Customer,
            'rules' => [
                'all' => [['type' => 'completed_orders', 'op' => 'gte', 'value' => 2]],
                'any' => [],
                'exclude' => [],
            ],
        ]);
        $frequent = $this->activeSegment('Frequent Buyers', [
            'actor_scope' => AudienceSegmentActor::Customer,
            'rules' => [
                'all' => [['type' => 'orders_per_30d', 'op' => 'gte', 'value' => 2]],
                'any' => [],
                'exclude' => [],
            ],
        ]);

        $service = app(SegmentServiceInterface::class);
        $this->assertTrue($service->matches($repeat, [], $customer)['matches']);
        $this->assertTrue($service->matches($frequent, [], $customer)['matches']);
    }

    public function test_inactive_lapsed_customer_fail_closed_without_purchase(): void
    {
        $customer = User::factory()->customer()->create();
        $lapsed = $this->activeSegment('Lapsed', [
            'actor_scope' => AudienceSegmentActor::Customer,
            'rules' => [
                'all' => [['type' => 'last_purchase_days', 'op' => 'gte', 'value' => 30]],
                'any' => [],
                'exclude' => [],
            ],
        ]);

        $this->assertFalse(app(SegmentServiceInterface::class)->matches($lapsed, [], $customer)['matches']);

        $product = $this->makePublicProduct('Old Buy');
        $this->createCompletedOrder($customer, $product, now()->subDays(45));

        $this->assertTrue(app(SegmentServiceInterface::class)->matches($lapsed, [], $customer)['matches']);
    }

    public function test_favourite_watchlist_and_affinity_segments(): void
    {
        config()->set('coffee.behaviour.profile.min_evidence_signals', 1);
        config()->set('coffee.behaviour.profile.recency_half_life_days', 3650);

        $customer = User::factory()->customer()->create();
        $category = ProductCategory::factory()->create(['is_active' => true]);
        $flavour = ProductFlavour::factory()->create(['name' => 'Hazelnut']);
        $product = $this->makePublicProduct('Frappe', $category);
        $product->flavours()->attach($flavour->id);

        ProductFavourite::query()->create([
            'customer_id' => $customer->id,
            'product_id' => $product->id,
        ]);

        $this->createCompletedOrder($customer, $product);
        $this->createCompletedOrder($customer, $product);
        app(PersonalisationProfileServiceInterface::class)->rebuildForCustomer($customer->id);

        $service = app(SegmentServiceInterface::class);

        $favourites = $this->activeSegment('Watchlist', [
            'rules' => [
                'all' => [['type' => 'has_favourites', 'op' => 'eq', 'value' => true]],
                'any' => [],
                'exclude' => [],
            ],
        ]);
        $categorySeg = $this->activeSegment('Category Fans', [
            'rules' => [
                'all' => [['type' => 'category_affinity', 'op' => 'includes', 'value' => $category->id]],
                'any' => [],
                'exclude' => [],
            ],
        ]);
        $flavourSeg = $this->activeSegment('Flavour Fans', [
            'rules' => [
                'all' => [['type' => 'flavour_affinity', 'op' => 'includes', 'value' => $flavour->id]],
                'any' => [],
                'exclude' => [],
            ],
        ]);

        $this->assertTrue($service->matches($favourites, [], $customer)['matches']);
        $this->assertTrue($service->matches($categorySeg, [], $customer)['matches']);
        $this->assertTrue($service->matches($flavourSeg, [], $customer)['matches']);
    }

    public function test_product_and_repeat_product_purchase_segments(): void
    {
        config()->set('coffee.behaviour.profile.min_evidence_signals', 1);

        $customer = User::factory()->customer()->create();
        $product = $this->makePublicProduct('Signature');
        $this->createCompletedOrder($customer, $product);
        $this->createCompletedOrder($customer, $product);
        app(PersonalisationProfileServiceInterface::class)->rebuildForCustomer($customer->id);

        $buyer = $this->activeSegment('Product Buyers', [
            'rules' => [
                'all' => [['type' => 'previous_purchase', 'op' => 'includes', 'value' => $product->id]],
                'any' => [],
                'exclude' => [],
            ],
        ]);
        $repeat = $this->activeSegment('Repeat Product Buyers', [
            'rules' => [
                'all' => [['type' => 'repeat_purchase', 'op' => 'includes', 'value' => $product->id]],
                'any' => [],
                'exclude' => [],
            ],
        ]);

        $service = app(SegmentServiceInterface::class);
        $this->assertTrue($service->matches($buyer, [], $customer)['matches']);
        $this->assertTrue($service->matches($repeat, [], $customer)['matches']);
    }

    public function test_all_any_exclude_composition(): void
    {
        $customer = User::factory()->customer()->create();
        $product = $this->makePublicProduct('Compose');
        $this->createCompletedOrder($customer, $product);

        $segment = $this->activeSegment('Compose', [
            'rules' => [
                'all' => [
                    ['type' => 'identity', 'op' => 'eq', 'value' => 'authenticated'],
                    ['type' => 'completed_orders', 'op' => 'gte', 'value' => 1],
                ],
                'any' => [
                    ['type' => 'previous_purchase', 'op' => 'includes', 'value' => $product->id],
                    ['type' => 'has_favourites', 'op' => 'eq', 'value' => true],
                ],
                'exclude' => [
                    ['type' => 'completed_orders', 'op' => 'gte', 'value' => 99],
                ],
            ],
        ]);

        $this->assertTrue(app(SegmentServiceInterface::class)->matches($segment, [], $customer)['matches']);
    }

    public function test_inactive_segment_and_missing_evidence_fail_closed(): void
    {
        $customer = User::factory()->customer()->create();
        $draft = AudienceSegment::factory()->create([
            'status' => AudienceSegmentStatus::Draft,
            'rules' => [
                'all' => [['type' => 'identity', 'op' => 'eq', 'value' => 'authenticated']],
                'any' => [],
                'exclude' => [],
            ],
        ]);

        $result = app(SegmentServiceInterface::class)->matches($draft, [], $customer);
        $this->assertFalse($result['matches']);
        $this->assertSame('inactive', $result['reason']);

        $needsEvidence = $this->activeSegment('Needs Frequency', [
            'rules' => [
                'all' => [['type' => 'orders_per_30d', 'op' => 'gte', 'value' => 1]],
                'any' => [],
                'exclude' => [],
            ],
        ]);
        $this->assertFalse(app(SegmentServiceInterface::class)->matches($needsEvidence, [], $customer)['matches']);
    }

    public function test_invalid_and_sensitive_rule_types_rejected(): void
    {
        $validator = app(TargetingRuleValidator::class);

        try {
            $validator->validateRuleGroups([
                'all' => [['type' => 'religion', 'op' => 'eq', 'value' => 'x']],
                'any' => [],
                'exclude' => [],
            ], $validator->segmentRuleTypes());
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('rules.all.0.type', $e->errors());
        }

        try {
            $validator->validateRuleGroups([
                'all' => [['type' => 'segment_matches', 'op' => 'eq', 'value' => 1]],
                'any' => [],
                'exclude' => [],
            ], $validator->segmentRuleTypes());
            $this->fail('Expected ValidationException for nested segments');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('rules.all.0.type', $e->errors());
        }
    }

    public function test_campaign_segment_matches_and_not_matches(): void
    {
        $customer = User::factory()->customer()->create();
        $product = $this->makePublicProduct('Camp');
        $this->createCompletedOrder($customer, $product);

        $repeatSeg = $this->activeSegment('Returning Customers Seg', [
            'actor_scope' => AudienceSegmentActor::Customer,
            'rules' => [
                'all' => [['type' => 'identity', 'op' => 'eq', 'value' => 'returning_customer']],
                'any' => [],
                'exclude' => [],
            ],
        ]);

        $matchCampaign = $this->makeActiveCampaign('Segment Match Camp', [
            'targeting_rules' => [
                'all' => [['type' => 'segment_matches', 'op' => 'eq', 'value' => $repeatSeg->id]],
                'any' => [],
                'exclude' => [],
            ],
        ]);
        $excludeCampaign = $this->makeActiveCampaign('Segment Exclude Camp', [
            'targeting_rules' => [
                'all' => [['type' => 'segment_not_matches', 'op' => 'eq', 'value' => $repeatSeg->id]],
                'any' => [],
                'exclude' => [],
            ],
            'priority' => 5,
        ]);

        $eligibility = app(CampaignEligibilityServiceInterface::class);
        $hit = $eligibility->eligible([
            'placement' => CampaignPlacement::Home->value,
            'visitor_key' => 'c1',
            'session_key' => 's1',
        ], $customer);
        $this->assertSame($matchCampaign->id, $hit['campaign']['id']);

        $guest = $eligibility->eligible([
            'placement' => CampaignPlacement::Home->value,
            'visitor_key' => 'guest_x',
            'session_key' => 's2',
        ]);
        $this->assertSame($excludeCampaign->id, $guest['campaign']['id']);
    }

    public function test_segment_modification_affects_campaign_eligibility(): void
    {
        $customer = User::factory()->customer()->create();
        $product = $this->makePublicProduct('Mutate');
        $this->createCompletedOrder($customer, $product);

        $segment = $this->activeSegment('Mutable', [
            'actor_scope' => AudienceSegmentActor::Customer,
            'rules' => [
                'all' => [['type' => 'completed_orders', 'op' => 'gte', 'value' => 1]],
                'any' => [],
                'exclude' => [],
            ],
        ]);

        $campaign = $this->makeActiveCampaign('Depends On Segment', [
            'targeting_rules' => [
                'all' => [['type' => 'segment_matches', 'op' => 'eq', 'value' => $segment->id]],
                'any' => [],
                'exclude' => [],
            ],
        ]);

        $eligibility = app(CampaignEligibilityServiceInterface::class);
        $before = $eligibility->eligible([
            'placement' => CampaignPlacement::Home->value,
            'visitor_key' => 'm1',
            'session_key' => 's1',
        ], $customer);
        $this->assertSame($campaign->id, $before['campaign']['id']);

        $segment->update([
            'rules' => [
                'all' => [['type' => 'completed_orders', 'op' => 'gte', 'value' => 50]],
                'any' => [],
                'exclude' => [],
            ],
        ]);
        app(SegmentServiceInterface::class)->flushMatchCache();

        $after = $eligibility->eligible([
            'placement' => CampaignPlacement::Home->value,
            'visitor_key' => 'm1',
            'session_key' => 's1',
        ], $customer);
        $this->assertNull($after['campaign']);
    }

    public function test_visitor_customer_isolation_and_tracking_disabled_order_segments(): void
    {
        config()->set('coffee.behaviour.enabled', false);

        $customerA = User::factory()->customer()->create();
        $customerB = User::factory()->customer()->create();
        $product = $this->makePublicProduct('Owned');
        $this->createCompletedOrder($customerA, $product);

        $segment = $this->activeSegment('Buyers Of Product', [
            'actor_scope' => AudienceSegmentActor::Customer,
            'rules' => [
                'all' => [['type' => 'previous_purchase', 'op' => 'includes', 'value' => $product->id]],
                'any' => [],
                'exclude' => [],
            ],
        ]);

        $service = app(SegmentServiceInterface::class);
        $this->assertTrue($service->matches($segment, [], $customerA)['matches']);
        $this->assertFalse($service->matches($segment, [], $customerB)['matches']);
        $this->assertFalse($service->matches($segment, ['visitor_key' => 'guest_iso'])['matches']);
    }

    public function test_matching_segments_read_contract_and_deterministic(): void
    {
        $customer = User::factory()->customer()->create();
        $this->activeSegment('Auth Both', [
            'rules' => [
                'all' => [['type' => 'identity', 'op' => 'eq', 'value' => 'authenticated']],
                'any' => [],
                'exclude' => [],
            ],
        ]);

        $service = app(SegmentServiceInterface::class);
        $first = $service->matchingSegments([], $customer);
        $second = $service->matchingSegments([], $customer);

        $this->assertSame($first, $second);
        $this->assertNotEmpty($first);
        $this->assertArrayHasKey('stable_key', $first[0]);
    }

    public function test_campaign_rejects_inactive_segment_reference(): void
    {
        $draft = AudienceSegment::factory()->create([
            'status' => AudienceSegmentStatus::Draft,
        ]);

        $this->expectException(ValidationException::class);

        app(CampaignRuleValidator::class)->validateTargetingRules([
            'all' => [['type' => 'segment_matches', 'op' => 'eq', 'value' => $draft->id]],
            'any' => [],
            'exclude' => [],
        ]);
    }

    public function test_high_intent_visitor_via_min_interactions(): void
    {
        config()->set('coffee.behaviour.profile.min_evidence_signals', 1);

        $visitor = 'intent'.Str::lower(Str::random(8));
        $product = $this->makePublicProduct('Intent');

        for ($i = 0; $i < 3; $i++) {
            CustomerBehaviourEvent::query()->create([
                'event_type' => BehaviourEventType::ProductViewed->value,
                'source' => BehaviourEventSource::Client->value,
                'visitor_key' => $visitor,
                'product_id' => $product->id,
                'product_category_id' => $product->product_category_id,
                'occurred_at' => now()->subMinutes($i),
            ]);
        }

        app(PersonalisationProfileServiceInterface::class)->rebuildForVisitor($visitor);

        $segment = $this->activeSegment('High Intent', [
            'actor_scope' => AudienceSegmentActor::Visitor,
            'rules' => [
                'all' => [
                    ['type' => 'min_interactions', 'op' => 'gte', 'value' => 2],
                    ['type' => 'recent_product', 'op' => 'includes', 'value' => $product->id],
                ],
                'any' => [],
                'exclude' => [],
            ],
        ]);

        $this->assertTrue(app(SegmentServiceInterface::class)->matches($segment, [
            'visitor_key' => $visitor,
        ])['matches']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function activeSegment(string $name, array $overrides = []): AudienceSegment
    {
        /** @var AudienceSegment $segment */
        $segment = AudienceSegment::factory()->active()->create(array_merge([
            'name' => $name,
            'actor_scope' => AudienceSegmentActor::Both,
        ], $overrides));

        app(SegmentServiceInterface::class)->flushMatchCache();

        return $segment;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeActiveCampaign(string $name, array $overrides = []): Campaign
    {
        /** @var Campaign $campaign */
        $campaign = Campaign::factory()->active()->popup()->create(array_merge([
            'name' => $name,
            'title' => $name.' Title',
            'status' => CampaignStatus::Active,
            'surface' => CampaignSurface::Popup,
            'cta_type' => CampaignCtaType::Close,
            'cta_label' => 'OK',
            'frequency_policy' => CampaignFrequencyPolicy::EverySession,
            'placement_rules' => [
                'placements' => ['global', 'home', 'menu', 'cart', 'product_detail', 'category', 'order_success'],
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
        ], $overrides));

        app(CampaignEligibilityServiceInterface::class)->flushConfigCache();

        return $campaign;
    }

    protected function makePublicProduct(string $name, ?ProductCategory $category = null): Product
    {
        $category ??= ProductCategory::factory()->create(['is_active' => true]);
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'name' => $name,
            'is_active' => true,
            'is_available' => true,
        ]);
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Regular',
            'price' => 120,
            'is_active' => true,
            'is_available' => true,
        ]);

        return $product->fresh(['category', 'variants', 'flavours']);
    }

    protected function createCompletedOrder(User $customer, Product $product, $completedAt = null): Order
    {
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::Completed,
            'payment_status' => PaymentStatus::Confirmed,
            'total_amount' => '150.00',
            'completed_at' => $completedAt ?? now(),
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $product->variants()->first()?->id,
            'product_name' => $product->name,
            'variant_name' => 'Regular',
            'unit_price' => '150.00',
            'quantity' => 1,
            'line_subtotal' => '150.00',
        ]);

        return $order;
    }
}
