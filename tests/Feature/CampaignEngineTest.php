<?php

namespace Tests\Feature;

use App\Enums\BehaviourEventSource;
use App\Enums\BehaviourEventType;
use App\Enums\CampaignCtaType;
use App\Enums\CampaignFrequencyPolicy;
use App\Enums\CampaignImpressionEvent;
use App\Enums\CampaignPlacement;
use App\Enums\CampaignStatus;
use App\Enums\CampaignSurface;
use App\Enums\CampaignTriggerType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Campaign;
use App\Models\CampaignImpression;
use App\Models\CustomerBehaviourEvent;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductFavourite;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Campaign\CampaignEligibilityServiceInterface;
use App\Services\Campaign\CampaignRuleValidator;
use App\Services\Personalisation\PersonalisationProfileServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CampaignEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('coffee.behaviour.campaigns.cache_ttl_seconds', 30);
        app(CampaignEligibilityServiceInterface::class)->flushConfigCache();
    }

    public function test_global_and_page_placement_matching(): void
    {
        $global = $this->makeActiveCampaign('Global Pop', [
            'placement_rules' => ['placements' => ['global'], 'category_ids' => [], 'product_ids' => [], 'product_tag_ids' => []],
            'priority' => 1,
        ]);
        $home = $this->makeActiveCampaign('Home Pop', [
            'placement_rules' => ['placements' => ['home'], 'category_ids' => [], 'product_ids' => [], 'product_tag_ids' => []],
            'priority' => 50,
        ]);

        $homeHit = $this->getJson(route('api.v1.campaigns.eligible', [
            'placement' => CampaignPlacement::Home->value,
            'visitor_key' => 'guest'.Str::lower(Str::random(10)),
            'session_key' => 'sess1',
        ]))->assertOk()->json('data.campaign');

        $this->assertSame($home->id, $homeHit['id']);

        $cartHit = $this->getJson(route('api.v1.campaigns.eligible', [
            'placement' => CampaignPlacement::Cart->value,
            'visitor_key' => 'guest'.Str::lower(Str::random(10)),
            'session_key' => 'sess2',
        ]))->assertOk()->json('data.campaign');

        $this->assertSame($global->id, $cartHit['id']);
    }

    public function test_category_and_product_specific_placement(): void
    {
        $category = ProductCategory::factory()->create(['is_active' => true]);
        $product = $this->makePublicProduct('Target Drink', $category);
        $other = $this->makePublicProduct('Other Drink');

        $categoryCampaign = $this->makeActiveCampaign('Category Pop', [
            'placement_rules' => [
                'placements' => ['category', 'product_detail'],
                'category_ids' => [$category->id],
                'product_ids' => [],
                'product_tag_ids' => [],
            ],
            'priority' => 20,
        ]);
        $productCampaign = $this->makeActiveCampaign('Product Pop', [
            'placement_rules' => [
                'placements' => ['product_detail'],
                'category_ids' => [],
                'product_ids' => [$product->id],
                'product_tag_ids' => [],
            ],
            'priority' => 40,
        ]);

        $productHit = app(CampaignEligibilityServiceInterface::class)->eligible([
            'placement' => CampaignPlacement::ProductDetail->value,
            'product_id' => $product->id,
            'category_id' => $category->id,
            'visitor_key' => 'guest_a',
            'session_key' => 's1',
        ]);

        $this->assertSame($productCampaign->id, $productHit['campaign']['id']);

        $otherHit = app(CampaignEligibilityServiceInterface::class)->eligible([
            'placement' => CampaignPlacement::ProductDetail->value,
            'product_id' => $other->id,
            'category_id' => $other->product_category_id,
            'visitor_key' => 'guest_b',
            'session_key' => 's2',
        ]);

        $this->assertNull($otherHit['campaign']);
        unset($categoryCampaign);
    }

    public function test_guest_and_authenticated_identity_targeting(): void
    {
        $guestOnly = $this->makeActiveCampaign('Guest Only', [
            'targeting_rules' => [
                'all' => [['type' => 'identity', 'op' => 'eq', 'value' => 'guest']],
                'any' => [],
                'exclude' => [],
            ],
        ]);
        $authOnly = $this->makeActiveCampaign('Auth Only', [
            'targeting_rules' => [
                'all' => [['type' => 'identity', 'op' => 'eq', 'value' => 'authenticated']],
                'any' => [],
                'exclude' => [],
            ],
            'priority' => 80,
        ]);

        $guest = app(CampaignEligibilityServiceInterface::class)->eligible([
            'placement' => CampaignPlacement::Home->value,
            'visitor_key' => 'guest_only',
            'session_key' => 's1',
        ]);
        $this->assertSame($guestOnly->id, $guest['campaign']['id']);

        $customer = User::factory()->customer()->create();
        $auth = app(CampaignEligibilityServiceInterface::class)->eligible([
            'placement' => CampaignPlacement::Home->value,
            'visitor_key' => 'ignored',
            'session_key' => 's2',
        ], $customer);
        $this->assertSame($authOnly->id, $auth['campaign']['id']);
    }

    public function test_returning_visitor_and_profile_affinity_rules(): void
    {
        config()->set('coffee.behaviour.profile.min_evidence_signals', 1);

        $visitor = 'return'.Str::lower(Str::random(8));
        $category = ProductCategory::factory()->create(['is_active' => true]);
        $product = $this->makePublicProduct('Affinity Drink', $category);

        CustomerBehaviourEvent::query()->create([
            'event_type' => BehaviourEventType::ProductViewed->value,
            'source' => BehaviourEventSource::Client->value,
            'visitor_key' => $visitor,
            'product_id' => $product->id,
            'product_category_id' => $category->id,
            'occurred_at' => now()->subDay(),
        ]);
        CustomerBehaviourEvent::query()->create([
            'event_type' => BehaviourEventType::CartItemAdded->value,
            'source' => BehaviourEventSource::Client->value,
            'visitor_key' => $visitor,
            'product_id' => $product->id,
            'product_category_id' => $category->id,
            'occurred_at' => now(),
        ]);

        app(PersonalisationProfileServiceInterface::class)->rebuildForVisitor($visitor);

        $campaign = $this->makeActiveCampaign('Affinity Pop', [
            'targeting_rules' => [
                'all' => [
                    ['type' => 'returning_visitor', 'op' => 'eq', 'value' => true],
                    ['type' => 'product_affinity', 'op' => 'includes', 'value' => $product->id],
                ],
                'any' => [],
                'exclude' => [],
            ],
        ]);

        $hit = app(CampaignEligibilityServiceInterface::class)->eligible([
            'placement' => CampaignPlacement::Home->value,
            'visitor_key' => $visitor,
            'session_key' => 's1',
        ]);

        $this->assertSame($campaign->id, $hit['campaign']['id']);
    }

    public function test_previous_and_repeat_purchase_and_cart_context(): void
    {
        $customer = User::factory()->customer()->create();
        $product = $this->makePublicProduct('Bought Twice');
        $cartMate = $this->makePublicProduct('Cart Mate');

        $this->createCompletedOrder($customer, $product);
        $this->createCompletedOrder($customer, $product);
        app(PersonalisationProfileServiceInterface::class)->rebuildForCustomer($customer->id);

        $purchaseCampaign = $this->makeActiveCampaign('Repeat Buyer', [
            'targeting_rules' => [
                'all' => [
                    ['type' => 'repeat_purchase', 'op' => 'includes', 'value' => $product->id],
                    ['type' => 'completed_orders', 'op' => 'gte', 'value' => 2],
                ],
                'any' => [],
                'exclude' => [],
            ],
            'priority' => 10,
        ]);

        $cartCampaign = $this->makeActiveCampaign('Cart Context', [
            'targeting_rules' => [
                'all' => [
                    ['type' => 'cart_contains_product', 'op' => 'includes', 'value' => $cartMate->id],
                ],
                'any' => [],
                'exclude' => [],
            ],
            'priority' => 90,
            'placement_rules' => [
                'placements' => ['cart'],
                'category_ids' => [],
                'product_ids' => [],
                'product_tag_ids' => [],
            ],
        ]);

        $purchaseHit = app(CampaignEligibilityServiceInterface::class)->eligible([
            'placement' => CampaignPlacement::Home->value,
            'session_key' => 's1',
        ], $customer);
        $this->assertSame($purchaseCampaign->id, $purchaseHit['campaign']['id']);

        $cartHit = app(CampaignEligibilityServiceInterface::class)->eligible([
            'placement' => CampaignPlacement::Cart->value,
            'cart_product_ids' => [$cartMate->id],
            'session_key' => 's2',
        ], $customer);
        $this->assertSame($cartCampaign->id, $cartHit['campaign']['id']);
    }

    public function test_location_rules_fail_closed_when_unavailable(): void
    {
        $campaign = $this->makeActiveCampaign('City Pop', [
            'targeting_rules' => [
                'all' => [
                    ['type' => 'location_city', 'op' => 'eq', 'value' => 'Jaipur'],
                ],
                'any' => [],
                'exclude' => [],
            ],
        ]);

        $missing = app(CampaignEligibilityServiceInterface::class)->eligible([
            'placement' => CampaignPlacement::Home->value,
            'visitor_key' => 'guest_loc',
            'session_key' => 's1',
            'location_available' => false,
            'location_city' => 'Jaipur',
        ]);
        $this->assertNull($missing['campaign']);

        $available = app(CampaignEligibilityServiceInterface::class)->eligible([
            'placement' => CampaignPlacement::Home->value,
            'visitor_key' => 'guest_loc2',
            'session_key' => 's2',
            'location_available' => true,
            'location_city' => 'Jaipur',
        ]);
        $this->assertSame($campaign->id, $available['campaign']['id']);
    }

    public function test_all_any_rules_and_invalid_rule_rejection(): void
    {
        $product = $this->makePublicProduct('Fav Drink');
        $customer = User::factory()->customer()->create();
        ProductFavourite::query()->create([
            'customer_id' => $customer->id,
            'product_id' => $product->id,
        ]);

        $campaign = $this->makeActiveCampaign('All Any', [
            'targeting_rules' => [
                'all' => [
                    ['type' => 'identity', 'op' => 'eq', 'value' => 'authenticated'],
                ],
                'any' => [
                    ['type' => 'favourite_product', 'op' => 'includes', 'value' => $product->id],
                    ['type' => 'completed_orders', 'op' => 'gte', 'value' => 99],
                ],
                'exclude' => [],
            ],
        ]);

        $hit = app(CampaignEligibilityServiceInterface::class)->eligible([
            'placement' => CampaignPlacement::Home->value,
            'session_key' => 's1',
        ], $customer);
        $this->assertSame($campaign->id, $hit['campaign']['id']);

        $this->expectException(ValidationException::class);
        app(CampaignRuleValidator::class)->validateTargetingRules([
            'all' => [
                ['type' => 'not_a_real_rule', 'op' => 'eq', 'value' => 1],
            ],
        ]);
    }

    public function test_schedule_priority_and_specificity_collision(): void
    {
        $category = ProductCategory::factory()->create(['is_active' => true]);
        $product = $this->makePublicProduct('Spec Drink', $category);

        $low = $this->makeActiveCampaign('Low Priority', [
            'priority' => 1,
            'placement_rules' => [
                'placements' => ['product_detail'],
                'category_ids' => [],
                'product_ids' => [],
                'product_tag_ids' => [],
            ],
        ]);
        $highSpecific = $this->makeActiveCampaign('High Specific', [
            'priority' => 1,
            'placement_rules' => [
                'placements' => ['product_detail'],
                'category_ids' => [],
                'product_ids' => [$product->id],
                'product_tag_ids' => [],
            ],
        ]);
        $future = $this->makeActiveCampaign('Future', [
            'priority' => 100,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(10),
        ]);
        $paused = $this->makeActiveCampaign('Paused', [
            'status' => CampaignStatus::Paused,
            'priority' => 200,
        ]);

        $hit = app(CampaignEligibilityServiceInterface::class)->eligible([
            'placement' => CampaignPlacement::ProductDetail->value,
            'product_id' => $product->id,
            'category_id' => $category->id,
            'visitor_key' => 'guest_coll',
            'session_key' => 's1',
        ]);

        $this->assertSame($highSpecific->id, $hit['campaign']['id']);
        unset($low, $future, $paused);
    }

    public function test_frequency_policies_session_day_actor_cooldown_and_max(): void
    {
        $service = app(CampaignEligibilityServiceInterface::class);

        $sessionCampaign = $this->makeActiveCampaign('Freq Session', [
            'frequency_policy' => CampaignFrequencyPolicy::OncePerSession,
            'placement_rules' => [
                'placements' => ['home'],
                'category_ids' => [],
                'product_ids' => [],
                'product_tag_ids' => [],
            ],
        ]);

        $first = $service->eligible([
            'placement' => 'home',
            'visitor_key' => 'freq_guest',
            'session_key' => 'session_a',
        ]);
        $this->assertSame($sessionCampaign->id, $first['campaign']['id'] ?? null);

        $service->recordInteraction([
            'campaign_id' => $sessionCampaign->id,
            'event_type' => CampaignImpressionEvent::Impression->value,
            'visitor_key' => 'freq_guest',
            'session_key' => 'session_a',
            'placement' => 'home',
            'request_id' => $first['request_id'],
        ]);

        $sameSession = $service->eligible([
            'placement' => 'home',
            'visitor_key' => 'freq_guest',
            'session_key' => 'session_a',
        ]);
        $this->assertNull($sameSession['campaign']);

        $newSession = $service->eligible([
            'placement' => 'home',
            'visitor_key' => 'freq_guest',
            'session_key' => 'session_b',
        ]);
        $this->assertSame($sessionCampaign->id, $newSession['campaign']['id'] ?? null);

        $sessionCampaign->update(['status' => CampaignStatus::Ended]);
        $service->flushConfigCache();

        $once = $this->makeActiveCampaign('Once Actor', [
            'frequency_policy' => CampaignFrequencyPolicy::OncePerActor,
            'placement_rules' => [
                'placements' => ['home'],
                'category_ids' => [],
                'product_ids' => [],
                'product_tag_ids' => [],
            ],
        ]);
        $service->recordInteraction([
            'campaign_id' => $once->id,
            'event_type' => CampaignImpressionEvent::Impression->value,
            'visitor_key' => 'once_guest',
            'session_key' => 'sx',
        ]);
        $blocked = $service->eligible([
            'placement' => 'home',
            'visitor_key' => 'once_guest',
            'session_key' => 'sy',
        ]);
        $this->assertNull($blocked['campaign']);

        $once->update(['status' => CampaignStatus::Ended]);
        $service->flushConfigCache();

        $day = $this->makeActiveCampaign('Once Day', [
            'frequency_policy' => CampaignFrequencyPolicy::OncePerDay,
            'placement_rules' => [
                'placements' => ['menu'],
                'category_ids' => [],
                'product_ids' => [],
                'product_tag_ids' => [],
            ],
        ]);
        $service->recordInteraction([
            'campaign_id' => $day->id,
            'event_type' => CampaignImpressionEvent::Impression->value,
            'visitor_key' => 'day_guest',
            'session_key' => 'd1',
        ]);
        $dayBlocked = $service->eligible([
            'placement' => 'menu',
            'visitor_key' => 'day_guest',
            'session_key' => 'd2',
        ]);
        $this->assertNull($dayBlocked['campaign']);

        $day->update(['status' => CampaignStatus::Ended]);
        $service->flushConfigCache();

        $cooldown = $this->makeActiveCampaign('Cooldown', [
            'frequency_policy' => CampaignFrequencyPolicy::Cooldown,
            'cooldown_hours' => 24,
            'placement_rules' => [
                'placements' => ['cart'],
                'category_ids' => [],
                'product_ids' => [],
                'product_tag_ids' => [],
            ],
        ]);
        CampaignImpression::query()->create([
            'campaign_id' => $cooldown->id,
            'visitor_key' => 'cool_guest',
            'event_type' => CampaignImpressionEvent::Impression,
            'occurred_at' => now()->subHours(2),
        ]);
        $coolBlocked = $service->eligible([
            'placement' => 'cart',
            'visitor_key' => 'cool_guest',
            'session_key' => 'c1',
        ]);
        $this->assertNull($coolBlocked['campaign']);

        $cooldown->update(['status' => CampaignStatus::Ended]);
        $service->flushConfigCache();

        $max = $this->makeActiveCampaign('Max Imp', [
            'frequency_policy' => CampaignFrequencyPolicy::MaxImpressions,
            'max_impressions' => 1,
            'placement_rules' => [
                'placements' => ['order_success'],
                'category_ids' => [],
                'product_ids' => [],
                'product_tag_ids' => [],
            ],
        ]);
        $service->recordInteraction([
            'campaign_id' => $max->id,
            'event_type' => CampaignImpressionEvent::Impression->value,
            'visitor_key' => 'max_guest',
            'session_key' => 'm1',
        ]);
        $maxBlocked = $service->eligible([
            'placement' => 'order_success',
            'visitor_key' => 'max_guest',
            'session_key' => 'm2',
        ]);
        $this->assertNull($maxBlocked['campaign']);
    }

    public function test_visitor_to_customer_claim_avoids_immediate_reshow(): void
    {
        $campaign = $this->makeActiveCampaign('Claim Freq', [
            'frequency_policy' => CampaignFrequencyPolicy::OncePerActor,
        ]);
        $visitor = 'claim'.Str::lower(Str::random(8));
        $customer = User::factory()->customer()->create();
        $service = app(CampaignEligibilityServiceInterface::class);

        $service->recordInteraction([
            'campaign_id' => $campaign->id,
            'event_type' => CampaignImpressionEvent::Impression->value,
            'visitor_key' => $visitor,
            'session_key' => 'pre',
        ]);

        $afterClaim = $service->eligible([
            'placement' => 'home',
            'visitor_key' => $visitor,
            'session_key' => 'post',
        ], $customer);

        $this->assertNull($afterClaim['campaign']);
    }

    public function test_impression_click_dismiss_events_and_api_privacy(): void
    {
        $campaign = $this->makeActiveCampaign('Track Me');
        $visitor = 'track'.Str::lower(Str::random(8));

        $eligible = $this->getJson(route('api.v1.campaigns.eligible', [
            'placement' => 'home',
            'visitor_key' => $visitor,
            'session_key' => 't1',
        ]))->assertOk()->json('data');

        $this->assertNotNull($eligible['campaign']);
        $encoded = json_encode($eligible);
        $this->assertStringNotContainsString('targeting_rules', $encoded);
        $this->assertStringNotContainsString('product_affinities', $encoded);
        $this->assertStringNotContainsString('internal_label', $encoded);

        $this->postJson(route('api.v1.behaviour.events.store'), [
            'event_type' => BehaviourEventType::CampaignImpression->value,
            'visitor_key' => $visitor,
            'metadata' => [
                'campaign_id' => $campaign->id,
                'request_id' => $eligible['request_id'],
                'placement' => 'home',
                'session_key' => 't1',
            ],
        ])->assertOk();

        $this->postJson(route('api.v1.behaviour.events.store'), [
            'event_type' => BehaviourEventType::CampaignClicked->value,
            'visitor_key' => $visitor,
            'metadata' => [
                'campaign_id' => $campaign->id,
                'request_id' => $eligible['request_id'],
                'placement' => 'home',
                'cta_type' => 'close',
            ],
        ])->assertOk();

        $this->postJson(route('api.v1.behaviour.events.store'), [
            'event_type' => BehaviourEventType::CampaignDismissed->value,
            'visitor_key' => $visitor,
            'metadata' => [
                'campaign_id' => $campaign->id,
                'request_id' => $eligible['request_id'],
                'placement' => 'home',
            ],
        ])->assertOk();

        $this->assertDatabaseCount('campaign_impressions', 3);

        $this->postJson(route('api.v1.behaviour.events.store'), [
            'event_type' => BehaviourEventType::CampaignImpression->value,
            'visitor_key' => $visitor,
            'metadata' => [
                'campaign_id' => $campaign->id,
            ],
        ])->assertStatus(422);
    }

    public function test_trigger_validation_and_inactive_exclusion_and_isolation(): void
    {
        $this->expectException(ValidationException::class);
        app(CampaignRuleValidator::class)->validateTriggerRules([
            'type' => CampaignTriggerType::Delay->value,
            'delay_ms' => 999999,
        ]);
    }

    public function test_paused_campaign_excluded_and_customers_isolated(): void
    {
        $paused = $this->makeActiveCampaign('Paused Now', [
            'status' => CampaignStatus::Paused,
            'priority' => 100,
        ]);
        $customerA = User::factory()->customer()->create();
        $customerB = User::factory()->customer()->create();
        $forA = $this->makeActiveCampaign('A Only', [
            'targeting_rules' => [
                'all' => [['type' => 'identity', 'op' => 'eq', 'value' => 'authenticated']],
                'any' => [],
                'exclude' => [],
            ],
            'priority' => 50,
        ]);

        ProductFavourite::query()->create([
            'customer_id' => $customerA->id,
            'product_id' => $this->makePublicProduct('A Fav')->id,
        ]);

        $hitA = app(CampaignEligibilityServiceInterface::class)->eligible([
            'placement' => 'home',
            'session_key' => 'a1',
        ], $customerA);
        $hitB = app(CampaignEligibilityServiceInterface::class)->eligible([
            'placement' => 'home',
            'session_key' => 'b1',
        ], $customerB);

        $this->assertNotNull($hitA['campaign']);
        $this->assertNotSame($paused->id, $hitA['campaign']['id']);
        $this->assertSame($forA->id, $hitA['campaign']['id']);
        $this->assertSame($forA->id, $hitB['campaign']['id']);
    }

    public function test_tracking_disabled_accepts_false_for_campaign_events(): void
    {
        config()->set('coffee.behaviour.enabled', false);
        $campaign = $this->makeActiveCampaign('Disabled Track');

        $this->postJson(route('api.v1.behaviour.events.store'), [
            'event_type' => BehaviourEventType::CampaignImpression->value,
            'visitor_key' => 'disabled'.Str::lower(Str::random(8)),
            'metadata' => [
                'campaign_id' => $campaign->id,
                'request_id' => (string) Str::uuid(),
                'placement' => 'home',
            ],
        ])->assertOk()
            ->assertJsonPath('data.accepted', false);
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
            'is_featured' => false,
            'is_new' => false,
            'is_bestseller' => false,
        ]);
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Regular',
            'price' => 120,
            'is_active' => true,
            'is_available' => true,
        ]);

        return $product->fresh(['category', 'variants']);
    }

    protected function createCompletedOrder(User $customer, Product $product): Order
    {
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::Completed,
            'payment_status' => PaymentStatus::Confirmed,
            'total_amount' => '150.00',
            'completed_at' => now(),
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
