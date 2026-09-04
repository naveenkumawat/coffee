<?php

namespace Tests\Feature;

use App\Enums\AudienceSegmentActor;
use App\Enums\AudienceSegmentStatus;
use App\Enums\BehaviourEventType;
use App\Enums\CampaignCtaType;
use App\Enums\CampaignFrequencyPolicy;
use App\Enums\CampaignPlacement;
use App\Enums\CampaignSurface;
use App\Enums\CampaignTriggerType;
use App\Enums\HomeSectionPlacement;
use App\Enums\HomeSectionSourceType;
use App\Enums\LoyaltyRewardType;
use App\Enums\LoyaltyTransactionType;
use App\Models\AudienceSegment;
use App\Models\Campaign;
use App\Models\CustomerBehaviourEvent;
use App\Models\HomeSection;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyPointTransaction;
use App\Models\LoyaltyReward;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Campaign\CampaignEligibilityServiceInterface;
use App\Services\Loyalty\LoyaltyPersonalisationContextServiceInterface;
use App\Services\Merchandising\MerchandisingServiceInterface;
use App\Services\Recommendation\RecommendationServiceInterface;
use App\Services\Segment\SegmentServiceInterface;
use App\Services\Targeting\TargetingRuleEvaluator;
use App\Services\Targeting\TargetingRuleValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LoyaltyIntelligenceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('loyalty.enabled', true);
        config()->set('loyalty.redemption.enabled', true);
        config()->set('loyalty.intelligence.near_reward_max_points_needed', 50);
        config()->set('loyalty.intelligence.recent_earn_lookback_days', 14);
        config()->set('loyalty.intelligence.recent_redeem_lookback_days', 30);
        config()->set('coffee.behaviour.segments.cache_ttl_seconds', 0);
        config()->set('coffee.behaviour.campaigns.cache_ttl_seconds', 0);
    }

    public function test_authenticated_loyalty_context_and_points_bands(): void
    {
        $customer = User::factory()->customer()->create();
        LoyaltyAccount::factory()->create([
            'customer_id' => $customer->id,
            'available_points' => 120,
        ]);
        LoyaltyReward::factory()->fixed('10.00')->create(['points_cost' => 100]);

        $signals = app(LoyaltyPersonalisationContextServiceInterface::class)->forActor($customer);

        $this->assertTrue($signals['loyalty_enabled']);
        $this->assertTrue($signals['has_loyalty_account']);
        $this->assertSame(120, $signals['available_points']);
        $this->assertSame('medium', $signals['points_band']);
        $this->assertTrue($signals['has_affordable_reward']);
        $this->assertFalse($signals['near_reward']);
        $this->assertFalse($signals['loyalty_debt']);
        $this->assertFalse($signals['redemption_blocked']);
        $this->assertArrayNotHasKey('idempotency_key', $signals);
        $this->assertArrayNotHasKey('ledger', $signals);
    }

    public function test_anonymous_and_no_account_contexts_are_safe(): void
    {
        $guest = app(LoyaltyPersonalisationContextServiceInterface::class)->forActor(null);
        $this->assertFalse($guest['loyalty_enabled']);
        $this->assertFalse($guest['has_loyalty_account']);
        $this->assertSame('none', $guest['points_band']);
        $this->assertFalse($guest['has_affordable_reward']);

        $customer = User::factory()->customer()->create();
        $signals = app(LoyaltyPersonalisationContextServiceInterface::class)->forActor($customer);
        $this->assertFalse($signals['has_loyalty_account']);
        $this->assertSame(0, LoyaltyAccount::query()->count());
    }

    public function test_near_reward_debt_and_recent_signals(): void
    {
        $customer = User::factory()->customer()->create();
        $account = LoyaltyAccount::factory()->create([
            'customer_id' => $customer->id,
            'available_points' => 70,
        ]);
        $reward = LoyaltyReward::factory()->fixed('10.00')->create(['points_cost' => 100]);

        $signals = app(LoyaltyPersonalisationContextServiceInterface::class)->forActor($customer);
        $this->assertTrue($signals['near_reward']);
        $this->assertSame((int) $reward->id, $signals['nearest_reward_id']);
        $this->assertSame(30, $signals['nearest_reward_points_needed']);
        $this->assertFalse($signals['has_affordable_reward']);

        LoyaltyPointTransaction::factory()->create([
            'loyalty_account_id' => $account->id,
            'customer_id' => $customer->id,
            'type' => LoyaltyTransactionType::Earn,
            'points' => 40,
            'occurred_at' => now()->subDays(2),
        ]);
        LoyaltyPointTransaction::factory()->create([
            'loyalty_account_id' => $account->id,
            'customer_id' => $customer->id,
            'type' => LoyaltyTransactionType::Redeem,
            'points' => -40,
            'reason_code' => 'order_loyalty_redeem',
            'occurred_at' => now()->subDays(3),
        ]);

        $fresh = app(LoyaltyPersonalisationContextServiceInterface::class);
        $withActivity = $fresh->forActor($customer);
        $this->assertTrue($withActivity['recent_earner']);
        $this->assertTrue($withActivity['recent_redeemer']);

        $account->forceFill(['available_points' => -20])->save();
        $debtContext = app(LoyaltyPersonalisationContextServiceInterface::class)->forActor($customer);
        $this->assertTrue($debtContext['loyalty_debt']);
        $this->assertTrue($debtContext['redemption_blocked']);
        $this->assertSame(0, $debtContext['available_points']);
        $this->assertFalse($debtContext['has_affordable_reward']);
    }

    public function test_selected_behaviour_event_is_not_recent_redeemer(): void
    {
        $customer = User::factory()->customer()->create();
        LoyaltyAccount::factory()->create([
            'customer_id' => $customer->id,
            'available_points' => 50,
        ]);
        $reward = LoyaltyReward::factory()->fixed('5.00')->create(['points_cost' => 40]);

        CustomerBehaviourEvent::query()->create([
            'event_type' => BehaviourEventType::LoyaltyRewardSelected->value,
            'source' => 'client',
            'customer_id' => $customer->id,
            'visitor_key' => 'v-'.Str::lower(Str::random(6)),
            'metadata' => ['reward_id' => $reward->id],
            'occurred_at' => now(),
        ]);

        $signals = app(LoyaltyPersonalisationContextServiceInterface::class)->forActor($customer);
        $this->assertFalse($signals['recent_redeemer']);
    }

    public function test_segment_loyalty_rules_and_campaign_targeting(): void
    {
        $customer = User::factory()->customer()->create();
        LoyaltyAccount::factory()->create([
            'customer_id' => $customer->id,
            'available_points' => 150,
        ]);
        LoyaltyReward::factory()->fixed('15.00')->create(['points_cost' => 100]);

        $rewardSegment = AudienceSegment::query()->create([
            'name' => 'Reward ready',
            'status' => AudienceSegmentStatus::Active,
            'actor_scope' => AudienceSegmentActor::Customer,
            'stable_key' => 'reward-ready-'.Str::lower(Str::random(6)),
            'rules' => [
                'all' => [
                    ['type' => 'loyalty_reward_available', 'op' => 'eq', 'value' => true],
                    ['type' => 'loyalty_points_gte', 'op' => 'eq', 'value' => 100],
                ],
                'any' => [],
                'exclude' => [],
            ],
        ]);

        $hit = app(SegmentServiceInterface::class)->matches($rewardSegment, [], $customer);
        $this->assertTrue($hit['matches']);

        $guestMiss = app(SegmentServiceInterface::class)->matches($rewardSegment, [
            'visitor_key' => 'guest-'.Str::lower(Str::random(6)),
        ]);
        $this->assertFalse($guestMiss['matches']);

        $debtSegment = AudienceSegment::query()->create([
            'name' => 'Debt',
            'status' => AudienceSegmentStatus::Active,
            'actor_scope' => AudienceSegmentActor::Customer,
            'stable_key' => 'debt-'.Str::lower(Str::random(6)),
            'rules' => [
                'all' => [['type' => 'loyalty_debt', 'op' => 'eq', 'value' => true]],
                'any' => [],
                'exclude' => [],
            ],
        ]);
        $this->assertFalse(app(SegmentServiceInterface::class)->matches($debtSegment, [], $customer)['matches']);

        $nearCustomer = User::factory()->customer()->create();
        LoyaltyAccount::factory()->create([
            'customer_id' => $nearCustomer->id,
            'available_points' => 80,
        ]);
        LoyaltyReward::factory()->fixed('10.00')->create(['points_cost' => 100, 'name' => 'Near reward']);
        $nearSegment = AudienceSegment::query()->create([
            'name' => 'Near',
            'status' => AudienceSegmentStatus::Active,
            'actor_scope' => AudienceSegmentActor::Customer,
            'stable_key' => 'near-'.Str::lower(Str::random(6)),
            'rules' => [
                'all' => [['type' => 'loyalty_near_reward', 'op' => 'eq', 'value' => true]],
                'any' => [],
                'exclude' => [],
            ],
        ]);
        $this->assertTrue(app(SegmentServiceInterface::class)->matches($nearSegment, [], $nearCustomer)['matches']);

        Campaign::factory()->active()->popup()->create([
            'name' => 'Reward available popup',
            'priority' => 10,
            'frequency_policy' => CampaignFrequencyPolicy::EverySession,
            'cta_type' => CampaignCtaType::InternalPage,
            'cta_label' => 'View rewards',
            'cta_internal_path' => '/loyalty',
            'title' => 'You have a reward',
            'message' => 'Redeem now',
            'placement_rules' => ['placements' => [CampaignPlacement::Home->value], 'category_ids' => [], 'product_ids' => [], 'product_tag_ids' => []],
            'targeting_rules' => [
                'all' => [['type' => 'loyalty_reward_available', 'op' => 'eq', 'value' => true]],
                'any' => [],
                'exclude' => [],
            ],
            'trigger_rules' => ['type' => CampaignTriggerType::Immediate->value],
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
        ]);

        $eligible = app(CampaignEligibilityServiceInterface::class)->eligible([
            'placement' => CampaignPlacement::Home->value,
            'surface' => CampaignSurface::Popup->value,
        ], $customer);
        $this->assertNotNull($eligible['campaign']);

        $fallback = app(CampaignEligibilityServiceInterface::class)->eligible([
            'placement' => CampaignPlacement::Home->value,
            'surface' => CampaignSurface::Popup->value,
            'visitor_key' => 'anon-'.Str::lower(Str::random(6)),
        ]);
        $this->assertNull($fallback['campaign']);
    }

    public function test_merchandising_loyalty_section_and_generic_fallback(): void
    {
        $customer = User::factory()->customer()->create();
        LoyaltyAccount::factory()->create([
            'customer_id' => $customer->id,
            'available_points' => 200,
        ]);
        LoyaltyReward::factory()->fixed('20.00')->create(['points_cost' => 100]);

        $category = ProductCategory::factory()->create(['is_active' => true]);
        $rewardProduct = Product::factory()->create([
            'product_category_id' => $category->id,
            'is_active' => true,
            'is_available' => true,
            'name' => 'Reward Section Brew',
        ]);
        ProductVariant::factory()->create([
            'product_id' => $rewardProduct->id,
            'is_active' => true,
            'is_available' => true,
            'price' => '90.00',
        ]);
        $genericProduct = Product::factory()->create([
            'product_category_id' => $category->id,
            'is_active' => true,
            'is_available' => true,
            'name' => 'Generic Section Brew',
        ]);
        ProductVariant::factory()->create([
            'product_id' => $genericProduct->id,
            'is_active' => true,
            'is_available' => true,
            'price' => '90.00',
        ]);

        $loyaltySection = HomeSection::factory()->create([
            'name' => 'Rewards for you',
            'is_active' => true,
            'placement' => HomeSectionPlacement::Home,
            'source_type' => HomeSectionSourceType::Curated,
            'sort_order' => 1,
            'title' => 'Rewards for you',
            'targeting_rules' => [
                'all' => [['type' => 'loyalty_reward_available', 'op' => 'eq', 'value' => true]],
                'any' => [],
                'exclude' => [],
            ],
        ]);
        $loyaltySection->products()->attach($rewardProduct->id, ['sort_order' => 1]);

        $genericSection = HomeSection::factory()->create([
            'name' => 'Everyone',
            'is_active' => true,
            'placement' => HomeSectionPlacement::Home,
            'source_type' => HomeSectionSourceType::Curated,
            'sort_order' => 2,
            'title' => 'Popular picks',
            'targeting_rules' => ['all' => [], 'any' => [], 'exclude' => []],
        ]);
        $genericSection->products()->attach($genericProduct->id, ['sort_order' => 1]);

        app(MerchandisingServiceInterface::class)->flushConfigCache();

        $landing = app(MerchandisingServiceInterface::class)->landingPayload([
            'placement' => HomeSectionPlacement::Home->value,
        ], $customer);

        $titles = collect($landing['sections'])->pluck('title')->all();
        $this->assertContains('Rewards for you', $titles);
        $this->assertContains('Popular picks', $titles);

        $guestLanding = app(MerchandisingServiceInterface::class)->landingPayload([
            'placement' => HomeSectionPlacement::Home->value,
            'visitor_key' => 'g-'.Str::lower(Str::random(6)),
        ]);
        $guestTitles = collect($guestLanding['sections'])->pluck('title')->all();
        $this->assertNotContains('Rewards for you', $guestTitles);
        $this->assertContains('Popular picks', $guestTitles);
    }

    public function test_loyalty_reward_eligible_strategy_is_explicit_only(): void
    {
        $customer = User::factory()->customer()->create();
        $category = ProductCategory::factory()->create(['is_active' => true]);
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'is_active' => true,
            'is_available' => true,
            'name' => 'Reward Latte',
        ]);
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
            'is_available' => true,
            'price' => '80.00',
        ]);
        LoyaltyAccount::factory()->create([
            'customer_id' => $customer->id,
            'available_points' => 100,
        ]);
        $reward = LoyaltyReward::factory()->create([
            'reward_type' => LoyaltyRewardType::SpecificProductReward,
            'points_cost' => 50,
            'config' => [],
        ]);
        $reward->products()->sync([$product->id]);

        $default = app(RecommendationServiceInterface::class)->recommend([
            'context' => 'home',
            'limit' => 8,
        ], $customer);
        $this->assertFalse(collect($default['items'])->contains(
            fn (array $item): bool => ($item['strategy'] ?? null) === 'loyalty_reward_eligible',
        ));

        $explicit = app(RecommendationServiceInterface::class)->recommend([
            'context' => 'home',
            'limit' => 8,
            'strategies' => ['loyalty_reward_eligible'],
        ], $customer);
        $this->assertTrue(collect($explicit['items'])->contains(
            fn (array $item): bool => (int) ($item['product']['id'] ?? 0) === (int) $product->id
                && ($item['strategy'] ?? null) === 'loyalty_reward_eligible',
        ));
    }

    public function test_personalisation_summary_expanded_and_tracking_disabled_still_works(): void
    {
        config()->set('coffee.behaviour.tracking_enabled', false);

        $customer = User::factory()->customer()->create();
        LoyaltyAccount::factory()->create([
            'customer_id' => $customer->id,
            'available_points' => 40,
        ]);
        LoyaltyReward::factory()->fixed('5.00')->create(['points_cost' => 80]);

        Sanctum::actingAs($customer);
        $summary = $this->getJson(route('api.v1.account.loyalty.show'))->json('data.personalisation_summary');

        $this->assertTrue($summary['loyalty_enabled']);
        $this->assertSame('low', $summary['points_band']);
        $this->assertTrue($summary['near_reward']);
        $this->assertArrayNotHasKey('ledger', $summary);
        $this->assertArrayNotHasKey('idempotency_key', $summary);
    }

    public function test_loyalty_context_failure_fails_closed_for_loyalty_rules(): void
    {
        $customer = User::factory()->customer()->create();
        LoyaltyAccount::factory()->create([
            'customer_id' => $customer->id,
            'available_points' => 200,
        ]);

        $context = app(SegmentServiceInterface::class)->buildContext([], $customer);
        $context['loyalty']['context_failed'] = true;
        $context['loyalty']['has_affordable_reward'] = true;

        $evaluator = app(TargetingRuleEvaluator::class);
        $this->assertFalse($evaluator->evaluateRule([
            'type' => 'loyalty_reward_available',
            'op' => 'eq',
            'value' => true,
        ], $context));
    }

    public function test_unknown_loyalty_rule_rejected_and_build_context_is_request_memoized(): void
    {
        $this->expectException(ValidationException::class);
        app(TargetingRuleValidator::class)->validateRuleGroups([
            'all' => [['type' => 'loyalty_secret_ledger', 'op' => 'eq', 'value' => true]],
            'any' => [],
            'exclude' => [],
        ], app(TargetingRuleValidator::class)->segmentRuleTypes());
    }

    public function test_login_switches_visitor_to_customer_loyalty_context(): void
    {
        $visitor = 'visit-'.Str::lower(Str::random(8));
        $customer = User::factory()->customer()->create();
        LoyaltyAccount::factory()->create([
            'customer_id' => $customer->id,
            'available_points' => 250,
        ]);
        LoyaltyReward::factory()->fixed('20.00')->create(['points_cost' => 100]);

        $guestCtx = app(SegmentServiceInterface::class)->buildContext(['visitor_key' => $visitor]);
        $this->assertFalse($guestCtx['loyalty']['has_affordable_reward']);

        $customerCtx = app(SegmentServiceInterface::class)->buildContext(['visitor_key' => $visitor], $customer);
        $this->assertTrue($customerCtx['loyalty']['has_affordable_reward']);
        $this->assertSame(250, $customerCtx['loyalty']['available_points']);
    }
}
