<?php

namespace Database\Seeders;

use App\Enums\AudienceSegmentActor;
use App\Enums\AudienceSegmentStatus;
use App\Models\AudienceSegment;
use App\Services\Targeting\TargetingRuleValidator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Local/testing dynamic audience segments covering supported rule types.
 * Never seed in production. Does not create permanent member lists.
 */
class DemoAudienceSegmentSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException(
                'DemoAudienceSegmentSeeder refused: demo segments must never be seeded outside local/testing (APP_ENV='.app()->environment().').',
            );
        }

        $validator = app(TargetingRuleValidator::class);
        $allowed = $validator->segmentRuleTypes();

        $definitions = [
            [
                'name' => '[Demo] Guests',
                'slug' => 'demo-guests',
                'description' => 'Example: Guest visitors only.',
                'actor_scope' => AudienceSegmentActor::Visitor,
                'rules' => $this->all([['type' => 'identity', 'op' => 'eq', 'value' => 'guest']]),
            ],
            [
                'name' => '[Demo] Logged-in Customers',
                'slug' => 'demo-logged-in-customers',
                'description' => 'Example: Authenticated customers.',
                'actor_scope' => AudienceSegmentActor::Customer,
                'rules' => $this->all([['type' => 'identity', 'op' => 'eq', 'value' => 'authenticated']]),
            ],
            [
                'name' => '[Demo] High Value Customers',
                'slug' => 'demo-high-value-customers',
                'description' => 'Example: Spend band high.',
                'actor_scope' => AudienceSegmentActor::Customer,
                'rules' => $this->all([['type' => 'spend_band', 'op' => 'eq', 'value' => 'high']]),
            ],
            [
                'name' => '[Demo] Coffee Lovers',
                'slug' => 'demo-coffee-lovers',
                'description' => 'Example: Hot coffee category affinity.',
                'actor_scope' => AudienceSegmentActor::Both,
                'rules' => $this->all([['type' => 'category_affinity', 'op' => 'eq', 'value' => 'hot-coffee']]),
            ],
            [
                'name' => '[Demo] Repeat Buyers',
                'slug' => 'demo-repeat-buyers',
                'description' => 'Example: Returning buyers with completed orders.',
                'actor_scope' => AudienceSegmentActor::Customer,
                'rules' => $this->all([
                    ['type' => 'returning_buyer', 'op' => 'eq', 'value' => true],
                    ['type' => 'completed_orders', 'op' => 'gte', 'value' => 2],
                ]),
            ],
            [
                'name' => '[Demo] Cart Interested',
                'slug' => 'demo-cart-interested',
                'description' => 'Example: Customers with favourites / interaction evidence.',
                'actor_scope' => AudienceSegmentActor::Both,
                'rules' => $this->all([
                    ['type' => 'has_favourites', 'op' => 'eq', 'value' => true],
                    ['type' => 'min_interactions', 'op' => 'gte', 'value' => 2],
                ]),
            ],
            [
                'name' => '[Demo] Near Loyalty Reward',
                'slug' => 'demo-near-loyalty-reward',
                'description' => 'Example: Near next loyalty reward.',
                'actor_scope' => AudienceSegmentActor::Customer,
                'rules' => $this->all([['type' => 'loyalty_near_reward', 'op' => 'eq', 'value' => true]]),
            ],
            [
                'name' => '[Demo] Loyalty Debt Customers',
                'slug' => 'demo-loyalty-debt-customers',
                'description' => 'Example: Negative loyalty balance.',
                'actor_scope' => AudienceSegmentActor::Customer,
                'rules' => $this->all([['type' => 'loyalty_debt', 'op' => 'eq', 'value' => true]]),
            ],
            [
                'name' => '[Demo] Recently Redeemed',
                'slug' => 'demo-recently-redeemed',
                'description' => 'Example: Recent loyalty redeemers.',
                'actor_scope' => AudienceSegmentActor::Customer,
                'rules' => $this->all([['type' => 'loyalty_recent_redeemer', 'op' => 'eq', 'value' => true]]),
            ],
            [
                'name' => '[Demo] Recently Earned',
                'slug' => 'demo-recently-earned',
                'description' => 'Example: Recent loyalty earners.',
                'actor_scope' => AudienceSegmentActor::Customer,
                'rules' => $this->all([['type' => 'loyalty_recent_earner', 'op' => 'eq', 'value' => true]]),
            ],
            [
                'name' => '[Demo] High Loyalty Points',
                'slug' => 'demo-high-loyalty-points',
                'description' => 'Example: Loyalty points band high.',
                'actor_scope' => AudienceSegmentActor::Customer,
                'rules' => $this->all([['type' => 'loyalty_points_band', 'op' => 'eq', 'value' => 'high']]),
            ],
            [
                'name' => '[Demo] Reward Available',
                'slug' => 'demo-reward-available',
                'description' => 'Example: Can afford at least one loyalty reward.',
                'actor_scope' => AudienceSegmentActor::Customer,
                'rules' => $this->all([['type' => 'loyalty_reward_available', 'op' => 'eq', 'value' => true]]),
            ],
            [
                'name' => '[Demo] Lapsed Customers',
                'slug' => 'demo-lapsed-customers',
                'description' => 'Example: Last purchase 30+ days ago.',
                'actor_scope' => AudienceSegmentActor::Customer,
                'rules' => $this->all([['type' => 'last_purchase_days', 'op' => 'gte', 'value' => 30]]),
            ],
            [
                'name' => '[Demo] Active Recent Buyers',
                'slug' => 'demo-active-recent-buyers',
                'description' => 'Example: Purchased within last 14 days.',
                'actor_scope' => AudienceSegmentActor::Customer,
                'rules' => $this->all([['type' => 'last_purchase_days', 'op' => 'lte', 'value' => 14]]),
            ],
            [
                'name' => '[Demo] Coffee Lovers AND High Value',
                'slug' => 'demo-coffee-lovers-and-high-value',
                'description' => 'Example: AND combination (all group).',
                'actor_scope' => AudienceSegmentActor::Customer,
                'rules' => $this->all([
                    ['type' => 'category_affinity', 'op' => 'eq', 'value' => 'hot-coffee'],
                    ['type' => 'spend_band', 'op' => 'eq', 'value' => 'high'],
                ]),
            ],
            [
                'name' => '[Demo] Guests OR New Customers',
                'slug' => 'demo-guests-or-new-customers',
                'description' => 'Example: OR combination (any group).',
                'actor_scope' => AudienceSegmentActor::Both,
                'rules' => [
                    'all' => [],
                    'any' => [
                        ['type' => 'identity', 'op' => 'eq', 'value' => 'guest'],
                        ['type' => 'first_order', 'op' => 'eq', 'value' => true],
                    ],
                    'exclude' => [],
                ],
            ],
            [
                'name' => '[Demo] Returning Coffee Lovers',
                'slug' => 'demo-returning-coffee-lovers',
                'description' => 'Example: Returning buyers with coffee affinity, excluding loyalty debt.',
                'actor_scope' => AudienceSegmentActor::Customer,
                'rules' => [
                    'all' => [
                        ['type' => 'returning_buyer', 'op' => 'eq', 'value' => true],
                        ['type' => 'category_affinity', 'op' => 'eq', 'value' => 'hot-coffee'],
                    ],
                    'any' => [],
                    'exclude' => [
                        ['type' => 'loyalty_debt', 'op' => 'eq', 'value' => true],
                    ],
                ],
            ],
            [
                'name' => '[Demo] Favourites Heavy',
                'slug' => 'demo-favourites-heavy',
                'description' => 'Example: Favourite count threshold.',
                'actor_scope' => AudienceSegmentActor::Customer,
                'rules' => $this->all([['type' => 'favourite_count', 'op' => 'gte', 'value' => 3]]),
            ],
            [
                'name' => '[Demo] Everyone (paused)',
                'slug' => 'demo-everyone-paused',
                'description' => 'Example: Paused segment for admin UI.',
                'status' => AudienceSegmentStatus::Paused,
                'actor_scope' => AudienceSegmentActor::Both,
                'rules' => $this->all([['type' => 'identity', 'op' => 'eq', 'value' => 'everyone']]),
            ],
        ];

        foreach ($definitions as $definition) {
            $rules = $validator->validateRuleGroups($definition['rules'], $allowed, 'rules');

            $segment = AudienceSegment::query()->withTrashed()->firstOrNew([
                'slug' => $definition['slug'],
            ]);

            if (! $segment->exists || blank($segment->stable_key)) {
                $segment->stable_key = 'seg_demo_'.Str::lower(Str::random(12));
            }

            $segment->fill([
                'name' => $definition['name'],
                'description' => $definition['description'],
                'status' => $definition['status'] ?? AudienceSegmentStatus::Active,
                'actor_scope' => $definition['actor_scope'],
                'rules' => $rules,
                'deleted_at' => null,
            ]);
            $segment->save();
        }
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
}
