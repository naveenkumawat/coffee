<?php

namespace Database\Seeders;

use App\Enums\AudienceSegmentStatus;
use App\Enums\CampaignCtaType;
use App\Enums\CampaignFrequencyPolicy;
use App\Enums\CampaignPlacement;
use App\Enums\CampaignStatus;
use App\Enums\CampaignSurface;
use App\Enums\CampaignTriggerType;
use App\Models\AudienceSegment;
use App\Models\Campaign;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Promotion;
use App\Services\Campaign\CampaignRuleValidator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Local/testing campaigns covering supported surfaces, audiences, schedules, triggers, CTAs.
 * Never seed in production.
 */
class DemoCampaignSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException(
                'DemoCampaignSeeder refused: demo campaigns must never be seeded outside local/testing (APP_ENV='.app()->environment().').',
            );
        }

        $validator = app(CampaignRuleValidator::class);
        $product = Product::query()->where('name', 'Cafe Latte')->first()
            ?? Product::query()->where('is_active', true)->first();
        $category = ProductCategory::query()->where('slug', 'hot-coffee')->first();
        $promotion = Promotion::query()->where('name', '[Demo] 10% Off Above ₹500')->first()
            ?? Promotion::query()->where('code', 'BULK500')->first();

        $highValue = AudienceSegment::query()
            ->where('slug', 'demo-high-value-customers')
            ->where('status', AudienceSegmentStatus::Active)
            ->first();
        $coffeeLovers = AudienceSegment::query()
            ->where('slug', 'demo-coffee-lovers')
            ->where('status', AudienceSegmentStatus::Active)
            ->first();
        $nearReward = AudienceSegment::query()
            ->where('slug', 'demo-near-loyalty-reward')
            ->where('status', AudienceSegmentStatus::Active)
            ->first();
        $guests = AudienceSegment::query()
            ->where('slug', 'demo-guests')
            ->where('status', AudienceSegmentStatus::Active)
            ->first();

        $definitions = [
            [
                'name' => '[Demo] Welcome Popup',
                'surface' => CampaignSurface::Popup,
                'status' => CampaignStatus::Active,
                'title' => 'Welcome to the café',
                'message' => 'Browse the menu and try a demo offer.',
                'cta_label' => 'Browse menu',
                'cta_type' => CampaignCtaType::InternalPage,
                'cta_internal_path' => '/menu',
                'priority' => 100,
                'frequency_policy' => CampaignFrequencyPolicy::OncePerActor,
                'placement' => [CampaignPlacement::Home->value],
                'targeting' => $this->identity('everyone'),
                'trigger' => $this->trigger(CampaignTriggerType::Immediate),
            ],
            [
                'name' => '[Demo] Guest Signup Prompt',
                'surface' => CampaignSurface::Popup,
                'status' => CampaignStatus::Active,
                'title' => 'Create an account',
                'message' => 'Sign in to earn loyalty points and save favourites.',
                'cta_label' => 'View account',
                'cta_type' => CampaignCtaType::InternalPage,
                'cta_internal_path' => '/account',
                'priority' => 90,
                'frequency_policy' => CampaignFrequencyPolicy::OncePerDay,
                'placement' => [CampaignPlacement::Home->value, CampaignPlacement::Menu->value],
                'targeting' => $guests
                    ? $this->segmentMatch((int) $guests->getKey())
                    : $this->identity('guest'),
                'trigger' => $this->trigger(CampaignTriggerType::Delay, delayMs: 2000),
            ],
            [
                'name' => '[Demo] Coffee Lovers Banner',
                'surface' => CampaignSurface::Banner,
                'status' => CampaignStatus::Active,
                'title' => 'For coffee lovers',
                'message' => 'Personalised picks from Hot Coffee.',
                'cta_label' => 'See category',
                'cta_type' => $category ? CampaignCtaType::Category : CampaignCtaType::InternalPage,
                'cta_category_id' => $category?->getKey(),
                'cta_internal_path' => $category ? null : '/menu',
                'priority' => 80,
                'frequency_policy' => CampaignFrequencyPolicy::EverySession,
                'placement' => [CampaignPlacement::Menu->value, CampaignPlacement::Home->value],
                'targeting' => $coffeeLovers
                    ? $this->segmentMatch((int) $coffeeLovers->getKey())
                    : $this->all([['type' => 'category_affinity', 'op' => 'eq', 'value' => 'hot-coffee']]),
                'trigger' => $this->trigger(CampaignTriggerType::Immediate),
            ],
            [
                'name' => '[Demo] Returning Customer Offer',
                'surface' => CampaignSurface::Inline,
                'status' => CampaignStatus::Active,
                'title' => 'Welcome back',
                'message' => 'A treat for returning coffee fans.',
                'cta_label' => 'View offer',
                'cta_type' => $promotion ? CampaignCtaType::Promotion : CampaignCtaType::InternalPage,
                'cta_promotion_id' => $promotion?->getKey(),
                'cta_internal_path' => $promotion ? null : '/menu',
                'priority' => 70,
                'frequency_policy' => CampaignFrequencyPolicy::OncePerSession,
                'placement' => [CampaignPlacement::Home->value],
                'targeting' => $this->all([
                    ['type' => 'returning_buyer', 'op' => 'eq', 'value' => true],
                    ['type' => 'category_affinity', 'op' => 'eq', 'value' => 'hot-coffee'],
                ]),
                'trigger' => $this->trigger(CampaignTriggerType::Immediate),
            ],
            [
                'name' => '[Demo] High Value Campaign',
                'surface' => CampaignSurface::Banner,
                'status' => CampaignStatus::Active,
                'title' => 'VIP coffee picks',
                'message' => 'Selected for high-value customers.',
                'cta_label' => 'Shop latte',
                'cta_type' => $product ? CampaignCtaType::Product : CampaignCtaType::Close,
                'cta_product_id' => $product?->getKey(),
                'priority' => 85,
                'frequency_policy' => CampaignFrequencyPolicy::Cooldown,
                'cooldown_hours' => 24,
                'placement' => [CampaignPlacement::Home->value],
                'targeting' => $highValue
                    ? $this->segmentMatch((int) $highValue->getKey())
                    : $this->all([['type' => 'spend_band', 'op' => 'eq', 'value' => 'high']]),
                'trigger' => $this->trigger(CampaignTriggerType::Immediate),
            ],
            [
                'name' => '[Demo] Rewards Reminder',
                'surface' => CampaignSurface::Popup,
                'status' => CampaignStatus::Active,
                'title' => 'You are close to a reward',
                'message' => 'One more order may unlock a loyalty treat.',
                'cta_label' => 'View rewards',
                'cta_type' => CampaignCtaType::InternalPage,
                'cta_internal_path' => '/loyalty',
                'priority' => 95,
                'frequency_policy' => CampaignFrequencyPolicy::OncePerDay,
                'placement' => [CampaignPlacement::Home->value, CampaignPlacement::OrderSuccess->value],
                'targeting' => $nearReward
                    ? $this->segmentMatch((int) $nearReward->getKey())
                    : $this->all([['type' => 'loyalty_near_reward', 'op' => 'eq', 'value' => true]]),
                'trigger' => $this->trigger(CampaignTriggerType::Delay, delayMs: 1500),
            ],
            [
                'name' => '[Demo] Cart Upsell',
                'surface' => CampaignSurface::Inline,
                'status' => CampaignStatus::Active,
                'title' => 'Complete your order',
                'message' => 'Add a pastry or upgrade your coffee.',
                'cta_label' => 'Browse menu',
                'cta_type' => CampaignCtaType::InternalPage,
                'cta_internal_path' => '/menu',
                'priority' => 75,
                'frequency_policy' => CampaignFrequencyPolicy::EverySession,
                'placement' => [CampaignPlacement::Cart->value],
                'targeting' => $this->identity('everyone'),
                'trigger' => $this->trigger(CampaignTriggerType::Immediate),
            ],
            [
                'name' => '[Demo] Checkout Reminder',
                'surface' => CampaignSurface::Banner,
                'status' => CampaignStatus::Active,
                'title' => 'Almost there',
                'message' => 'Confirm fulfilment and payment to place your order.',
                'cta_label' => 'Close',
                'cta_type' => CampaignCtaType::Close,
                'priority' => 60,
                'frequency_policy' => CampaignFrequencyPolicy::OncePerSession,
                'placement' => [CampaignPlacement::Checkout->value],
                'targeting' => $this->identity('authenticated'),
                'trigger' => $this->trigger(CampaignTriggerType::Immediate),
            ],
            [
                'name' => '[Demo] Weekend Campaign',
                'surface' => CampaignSurface::Landing,
                'status' => CampaignStatus::Active,
                'title' => 'Weekend specials',
                'message' => 'Try weekend coffee offers while they last.',
                'cta_label' => 'See menu',
                'cta_type' => CampaignCtaType::InternalPage,
                'cta_internal_path' => '/menu',
                'priority' => 55,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addDays(7),
                'frequency_policy' => CampaignFrequencyPolicy::MaxImpressions,
                'max_impressions' => 5,
                'placement' => [CampaignPlacement::Global->value],
                'targeting' => $this->identity('everyone'),
                'trigger' => $this->trigger(CampaignTriggerType::Scroll, scrollPercent: 40),
            ],
            [
                'name' => '[Demo] Product Views Nudge',
                'surface' => CampaignSurface::Popup,
                'status' => CampaignStatus::Active,
                'title' => 'Still browsing?',
                'message' => 'Here is a highlight after a few product views.',
                'cta_label' => 'Open product',
                'cta_type' => $product ? CampaignCtaType::Product : CampaignCtaType::Close,
                'cta_product_id' => $product?->getKey(),
                'priority' => 50,
                'frequency_policy' => CampaignFrequencyPolicy::OncePerSession,
                'placement' => [CampaignPlacement::Menu->value, CampaignPlacement::ProductDetail->value],
                'targeting' => $this->identity('everyone'),
                'trigger' => $this->trigger(CampaignTriggerType::ProductViews, productViewCount: 3),
            ],
            [
                'name' => '[Demo] Future Scheduled Campaign',
                'surface' => CampaignSurface::Banner,
                'status' => CampaignStatus::Active,
                'title' => 'Coming soon',
                'message' => 'A scheduled campaign that starts later.',
                'cta_label' => 'Close',
                'cta_type' => CampaignCtaType::Close,
                'priority' => 20,
                'starts_at' => now()->addDays(10),
                'ends_at' => now()->addDays(20),
                'frequency_policy' => CampaignFrequencyPolicy::EverySession,
                'placement' => [CampaignPlacement::Home->value],
                'targeting' => $this->identity('everyone'),
                'trigger' => $this->trigger(CampaignTriggerType::Immediate),
            ],
            [
                'name' => '[Demo] Expired Campaign',
                'surface' => CampaignSurface::Banner,
                'status' => CampaignStatus::Ended,
                'title' => 'Expired promo',
                'message' => 'This campaign window has ended.',
                'cta_label' => 'Close',
                'cta_type' => CampaignCtaType::Close,
                'priority' => 10,
                'starts_at' => now()->subMonths(2),
                'ends_at' => now()->subMonth(),
                'frequency_policy' => CampaignFrequencyPolicy::EverySession,
                'placement' => [CampaignPlacement::Home->value],
                'targeting' => $this->identity('everyone'),
                'trigger' => $this->trigger(CampaignTriggerType::Immediate),
            ],
            [
                'name' => '[Demo] Paused Campaign',
                'surface' => CampaignSurface::Inline,
                'status' => CampaignStatus::Paused,
                'title' => 'Paused demo',
                'message' => 'Paused for admin UI demos.',
                'cta_label' => 'Close',
                'cta_type' => CampaignCtaType::Close,
                'priority' => 5,
                'frequency_policy' => CampaignFrequencyPolicy::EverySession,
                'placement' => [CampaignPlacement::Menu->value],
                'targeting' => $this->identity('everyone'),
                'trigger' => $this->trigger(CampaignTriggerType::Immediate),
            ],
            [
                'name' => '[Demo] Draft Campaign',
                'surface' => CampaignSurface::Popup,
                'status' => CampaignStatus::Draft,
                'title' => 'Draft only',
                'message' => 'Not visible to customers until activated.',
                'cta_label' => 'Close',
                'cta_type' => CampaignCtaType::Close,
                'priority' => 1,
                'frequency_policy' => CampaignFrequencyPolicy::OncePerActor,
                'placement' => [CampaignPlacement::Home->value],
                'targeting' => $this->identity('everyone'),
                'trigger' => $this->trigger(CampaignTriggerType::Immediate),
            ],
            [
                'name' => '[Demo] Order Success Referral Nudge',
                'surface' => CampaignSurface::Inline,
                'status' => CampaignStatus::Active,
                'title' => 'Share the café',
                'message' => 'Invite friends after your order.',
                'cta_label' => 'Open account',
                'cta_type' => CampaignCtaType::InternalPage,
                'cta_internal_path' => '/account',
                'priority' => 65,
                'frequency_policy' => CampaignFrequencyPolicy::OncePerActor,
                'placement' => [CampaignPlacement::OrderSuccess->value],
                'targeting' => $this->identity('authenticated'),
                'trigger' => $this->trigger(CampaignTriggerType::Immediate),
            ],
        ];

        foreach ($definitions as $definition) {
            $placement = $validator->validatePlacementRules([
                'placements' => $definition['placement'],
                'category_ids' => [],
                'product_ids' => [],
                'product_tag_ids' => [],
            ]);
            $targeting = $validator->validateTargetingRules($definition['targeting']);
            $trigger = $validator->validateTriggerRules($definition['trigger']);

            $payload = [
                'name' => $definition['name'],
                'internal_label' => Str::slug($definition['name']),
                'status' => $definition['status'],
                'surface' => $definition['surface'],
                'title' => $definition['title'],
                'message' => $definition['message'],
                'cta_label' => $definition['cta_label'],
                'cta_type' => $definition['cta_type'],
                'cta_product_id' => $definition['cta_product_id'] ?? null,
                'cta_category_id' => $definition['cta_category_id'] ?? null,
                'cta_promotion_id' => $definition['cta_promotion_id'] ?? null,
                'cta_internal_path' => $definition['cta_internal_path'] ?? null,
                'priority' => $definition['priority'],
                'starts_at' => $definition['starts_at'] ?? now()->subDay(),
                'ends_at' => $definition['ends_at'] ?? now()->addDays(30),
                'frequency_policy' => $definition['frequency_policy'],
                'cooldown_hours' => $definition['cooldown_hours'] ?? null,
                'max_impressions' => $definition['max_impressions'] ?? null,
                'placement_rules' => $placement,
                'targeting_rules' => $targeting,
                'trigger_rules' => $trigger,
            ];

            $validator->assertCtaPayload([
                'cta_type' => $payload['cta_type']->value,
                'cta_product_id' => $payload['cta_product_id'],
                'cta_category_id' => $payload['cta_category_id'],
                'cta_promotion_id' => $payload['cta_promotion_id'],
                'cta_internal_path' => $payload['cta_internal_path'],
            ]);

            $campaign = Campaign::query()->withTrashed()->firstOrNew([
                'name' => $definition['name'],
            ]);

            if (! $campaign->exists || blank($campaign->attribution_key)) {
                $campaign->attribution_key = 'cmp_demo_'.Str::lower(Str::random(12));
            }

            $campaign->fill($payload);
            $campaign->deleted_at = null;
            $campaign->save();
        }
    }

    /**
     * @return array{all: list<array<string, mixed>>, any: list<array<string, mixed>>, exclude: list<array<string, mixed>>}
     */
    protected function identity(string $value): array
    {
        return $this->all([['type' => 'identity', 'op' => 'eq', 'value' => $value]]);
    }

    /**
     * @return array{all: list<array<string, mixed>>, any: list<array<string, mixed>>, exclude: list<array<string, mixed>>}
     */
    protected function segmentMatch(int $segmentId): array
    {
        return $this->all([['type' => 'segment_matches', 'op' => 'eq', 'value' => $segmentId]]);
    }

    /**
     * @param  list<array{type: string, op: string, value: mixed}>  $rules
     * @return array{all: list<array<string, mixed>>, any: list<array<string, mixed>>, exclude: list<array<string, mixed>>}
     */
    protected function all(array $rules): array
    {
        return [
            'all' => $rules,
            'any' => [],
            'exclude' => [],
        ];
    }

    /**
     * @return array{type: string, delay_ms: int|null, scroll_percent: int|null, product_view_count: int|null}
     */
    protected function trigger(
        CampaignTriggerType $type,
        ?int $delayMs = null,
        ?int $scrollPercent = null,
        ?int $productViewCount = null,
    ): array {
        return [
            'type' => $type->value,
            'delay_ms' => $delayMs,
            'scroll_percent' => $scrollPercent,
            'product_view_count' => $productViewCount,
        ];
    }
}
