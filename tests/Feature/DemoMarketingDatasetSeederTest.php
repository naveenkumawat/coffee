<?php

namespace Tests\Feature;

use App\Models\AudienceSegment;
use App\Models\Campaign;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyReward;
use App\Models\Promotion;
use App\Models\User;
use App\Services\Campaign\CampaignRuleValidator;
use App\Services\Targeting\TargetingRuleValidator;
use App\Support\Targeting\TargetingRuleTemplates;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoAudienceSegmentSeeder;
use Database\Seeders\DemoCampaignSeeder;
use Database\Seeders\DemoLoyaltySeeder;
use Database\Seeders\DemoPromotionSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use RuntimeException;
use Tests\TestCase;

class DemoMarketingDatasetSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_marketing_seeders_are_blocked_outside_local_testing(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DemoSeeder refused');

        $this->app->make(DemoSeeder::class)->run();
    }

    public function test_demo_seeder_creates_valid_promotion_loyalty_campaign_and_segment_variations(): void
    {
        Config::set('loyalty.enabled', true);

        $this->seed(DatabaseSeeder::class);

        $demoPromotions = Promotion::query()->where('name', 'like', '[Demo]%')->get();
        $this->assertGreaterThanOrEqual(15, $demoPromotions->count());

        $this->assertTrue($demoPromotions->contains(fn (Promotion $p): bool => $p->type?->value === 'automatic'));
        $this->assertTrue($demoPromotions->contains(fn (Promotion $p): bool => $p->type?->value === 'coupon'));
        $this->assertTrue($demoPromotions->contains(fn (Promotion $p): bool => $p->discount_type?->value === 'percentage'));
        $this->assertTrue($demoPromotions->contains(fn (Promotion $p): bool => $p->discount_type?->value === 'fixed'));
        $this->assertTrue($demoPromotions->contains(fn (Promotion $p): bool => $p->fulfilment_scope?->value === 'takeaway'));
        $this->assertTrue($demoPromotions->contains(fn (Promotion $p): bool => $p->fulfilment_scope?->value === 'delivery'));
        $this->assertTrue($demoPromotions->contains(fn (Promotion $p): bool => $p->first_order_only === true));
        $this->assertTrue($demoPromotions->contains(fn (Promotion $p): bool => $p->stackable === true));
        $this->assertTrue($demoPromotions->contains(fn (Promotion $p): bool => $p->is_active === false));
        $this->assertTrue(
            Promotion::query()
                ->where('name', '[Demo] Espresso Product Discount')
                ->whereHas('products')
                ->exists(),
        );
        $this->assertTrue(
            Promotion::query()
                ->where('name', '[Demo] Cappuccino Category Discount')
                ->whereHas('productCategories')
                ->exists(),
        );

        $segments = AudienceSegment::query()->where('name', 'like', '[Demo]%')->get();
        $this->assertGreaterThanOrEqual(12, $segments->count());

        $validator = app(TargetingRuleValidator::class);
        foreach ($segments as $segment) {
            $validator->validateRuleGroups(
                $segment->rules ?? [],
                $validator->segmentRuleTypes(),
                'rules',
            );
        }

        $rewards = LoyaltyReward::query()->where('name', 'like', '[Demo]%')->get();
        $this->assertGreaterThanOrEqual(8, $rewards->count());
        $this->assertTrue($rewards->contains(fn (LoyaltyReward $r): bool => $r->reward_type?->value === 'fixed_order_discount'));
        $this->assertTrue($rewards->contains(fn (LoyaltyReward $r): bool => $r->reward_type?->value === 'percentage_order_discount'));
        $this->assertTrue($rewards->contains(fn (LoyaltyReward $r): bool => $r->reward_type?->value === 'free_base_product'));
        $this->assertTrue($rewards->contains(fn (LoyaltyReward $r): bool => $r->status?->value === 'paused'));
        $this->assertTrue($rewards->contains(fn (LoyaltyReward $r): bool => $r->status?->value === 'archived'));

        $this->assertSame(
            0,
            (int) LoyaltyAccount::query()
                ->where('customer_id', User::query()->where('email', 'demo.loyalty.zero@coffee.local')->value('id'))
                ->value('available_points'),
        );
        $this->assertSame(
            650,
            (int) LoyaltyAccount::query()
                ->where('customer_id', User::query()->where('email', 'demo.loyalty.rich@coffee.local')->value('id'))
                ->value('available_points'),
        );
        $this->assertSame(
            -40,
            (int) LoyaltyAccount::query()
                ->where('customer_id', User::query()->where('email', 'demo.loyalty.debt@coffee.local')->value('id'))
                ->value('available_points'),
        );

        $campaigns = Campaign::query()->where('name', 'like', '[Demo]%')->get();
        $this->assertGreaterThanOrEqual(12, $campaigns->count());

        $campaignValidator = app(CampaignRuleValidator::class);
        foreach ($campaigns as $campaign) {
            $campaignValidator->validatePlacementRules($campaign->placement_rules ?? []);
            $campaignValidator->validateTargetingRules($campaign->targeting_rules ?? []);
            $campaignValidator->validateTriggerRules($campaign->trigger_rules ?? []);
        }

        $this->assertTrue($campaigns->contains(fn (Campaign $c): bool => $c->surface?->value === 'popup'));
        $this->assertTrue($campaigns->contains(fn (Campaign $c): bool => $c->surface?->value === 'banner'));
        $this->assertTrue($campaigns->contains(fn (Campaign $c): bool => $c->surface?->value === 'inline'));
        $this->assertTrue($campaigns->contains(fn (Campaign $c): bool => $c->status?->value === 'paused'));
        $this->assertTrue($campaigns->contains(fn (Campaign $c): bool => $c->status?->value === 'ended'));
        $this->assertTrue($campaigns->contains(fn (Campaign $c): bool => $c->status?->value === 'draft'));
    }

    public function test_demo_marketing_seed_is_idempotent_on_rerun(): void
    {
        Config::set('loyalty.enabled', true);

        $this->seed(DatabaseSeeder::class);
        $promoCount = Promotion::query()->where('name', 'like', '[Demo]%')->count();
        $segmentCount = AudienceSegment::query()->where('name', 'like', '[Demo]%')->count();
        $campaignCount = Campaign::query()->where('name', 'like', '[Demo]%')->count();
        $rewardCount = LoyaltyReward::query()->where('name', 'like', '[Demo]%')->count();
        $richPoints = (int) LoyaltyAccount::query()
            ->where('customer_id', User::query()->where('email', 'demo.loyalty.rich@coffee.local')->value('id'))
            ->value('available_points');

        $this->seed([
            DemoPromotionSeeder::class,
            DemoAudienceSegmentSeeder::class,
            DemoLoyaltySeeder::class,
            DemoCampaignSeeder::class,
        ]);

        $this->assertSame($promoCount, Promotion::query()->where('name', 'like', '[Demo]%')->count());
        $this->assertSame($segmentCount, AudienceSegment::query()->where('name', 'like', '[Demo]%')->count());
        $this->assertSame($campaignCount, Campaign::query()->where('name', 'like', '[Demo]%')->count());
        $this->assertSame($rewardCount, LoyaltyReward::query()->where('name', 'like', '[Demo]%')->count());
        $this->assertSame(
            $richPoints,
            (int) LoyaltyAccount::query()
                ->where('customer_id', User::query()->where('email', 'demo.loyalty.rich@coffee.local')->value('id'))
                ->value('available_points'),
        );
    }

    public function test_targeting_templates_validate_against_supported_schemas(): void
    {
        $templates = app(TargetingRuleTemplates::class);
        $validator = app(TargetingRuleValidator::class);

        foreach ($templates->forScope('segment') as $template) {
            $validator->validateRuleGroups(
                $template['rules'],
                $validator->segmentRuleTypes(),
                'rules',
            );
        }

        foreach ($templates->forScope('campaign') as $template) {
            $validator->validateRuleGroups(
                $template['rules'],
                $validator->campaignRuleTypes(),
                'targeting_rules',
            );
        }

        $this->assertNotEmpty($templates->optionReference('segment'));
        $this->assertContains(
            'segment_matches',
            array_column($templates->optionReference('campaign'), 'key'),
        );
        $this->assertNotContains(
            'segment_matches',
            array_column($templates->optionReference('segment'), 'key'),
        );
    }
}
