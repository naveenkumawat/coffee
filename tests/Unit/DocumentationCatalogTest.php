<?php

namespace Tests\Unit;

use App\Enums\CampaignCtaType;
use App\Enums\CampaignFrequencyPolicy;
use App\Enums\CampaignPlacement;
use App\Enums\CampaignStatus;
use App\Enums\CampaignSurface;
use App\Enums\CampaignTriggerType;
use App\Enums\LoyaltyRewardType;
use App\Enums\PromotionFulfilmentScope;
use App\Support\Documentation\DocumentationCatalog;
use Tests\TestCase;

class DocumentationCatalogTest extends TestCase
{
    public function test_each_role_only_sees_its_own_modules(): void
    {
        $catalog = new DocumentationCatalog;

        foreach ($catalog->roles() as $role) {
            $modules = $catalog->modulesForRole($role);

            $this->assertNotEmpty($modules, "Role {$role} has no documentation modules.");

            foreach ($modules as $module) {
                $this->assertContains($role, $module['roles']);
            }
        }
    }

    public function test_owner_and_manager_resolve_to_administrator_modules(): void
    {
        $catalog = new DocumentationCatalog;
        $administrator = $catalog->modulesForRole('administrator');

        $this->assertSame($administrator, $catalog->modulesForRole('owner'));
        $this->assertSame($administrator, $catalog->modulesForRole('manager'));
        $this->assertSame($administrator, $catalog->modulesForRole('  Administrator  '));
    }

    public function test_unknown_role_returns_no_modules(): void
    {
        $catalog = new DocumentationCatalog;

        $this->assertSame([], $catalog->modulesForRole('customer'));
        $this->assertNull($catalog->findModule('customer', 'dashboard'));
        $this->assertSame([], $catalog->search('customer', 'orders'));
    }

    public function test_slugs_are_unique_within_a_role(): void
    {
        $catalog = new DocumentationCatalog;

        foreach ($catalog->roles() as $role) {
            $slugs = array_column($catalog->modulesForRole($role), 'slug');

            $this->assertSame(array_unique($slugs), $slugs, "Role {$role} has duplicate module slugs.");
        }
    }

    public function test_every_module_exposes_the_full_documented_shape(): void
    {
        $catalog = new DocumentationCatalog;

        foreach ($catalog->modules() as $module) {
            $this->assertSame([
                'slug', 'title', 'group', 'roles', 'tags', 'overview', 'how_it_works',
                'how_to_use', 'how_to_configure', 'conditions', 'examples', 'options',
                'notes', 'demo_samples', 'help_anchor',
            ], array_keys($module));

            $this->assertNotSame('', $module['slug']);
            $this->assertNotSame('', $module['title']);
            $this->assertNotSame('', $module['overview']);
            $this->assertContains($module['group'], $catalog->groupOrder());
            $this->assertNotEmpty($module['how_it_works']);

            foreach ($module['conditions'] as $condition) {
                $this->assertSame(['if', 'then'], array_keys($condition));
            }

            foreach ($module['examples'] as $example) {
                $this->assertSame(['title', 'body'], array_keys($example));
            }

            foreach ($module['options'] as $option) {
                $this->assertSame(['name', 'what', 'why', 'when', 'example'], array_keys($option));
            }
        }
    }

    public function test_find_module_is_scoped_to_the_role_and_ignores_slug_casing(): void
    {
        $catalog = new DocumentationCatalog;

        $this->assertSame('Offers & Promotions', $catalog->findModule('administrator', 'PROMOTIONS')['title']);
        $this->assertNull($catalog->findModule('waiter', 'promotions'));
        $this->assertNull($catalog->findModule('administrator', 'no-such-module'));
    }

    public function test_find_module_returns_the_variant_belonging_to_the_requesting_role(): void
    {
        $catalog = new DocumentationCatalog;

        $this->assertSame('Operations', $catalog->findModule('administrator', 'tables')['group']);
        $this->assertSame('Dining', $catalog->findModule('waiter', 'tables')['group']);
    }

    public function test_search_matches_title_overview_and_tags_case_insensitively(): void
    {
        $catalog = new DocumentationCatalog;

        $this->assertSame(['promotions'], array_column($catalog->search('administrator', 'COUPONS'), 'slug'));
        $this->assertContains('gst-tax', array_column($catalog->search('administrator', 'gstin'), 'slug'));
        $this->assertSame([], $catalog->search('administrator', 'zzzz-no-match'));
    }

    public function test_blank_search_returns_every_module_for_the_role(): void
    {
        $catalog = new DocumentationCatalog;

        $this->assertSame($catalog->modulesForRole('barista'), $catalog->search('barista', '   '));
    }

    public function test_promotion_scope_options_cover_every_fulfilment_scope(): void
    {
        $this->assertSame(
            count(PromotionFulfilmentScope::cases()),
            $this->countOptionsWithPrefix('administrator', 'promotions', 'Scope: '),
        );
    }

    public function test_campaign_options_cover_every_campaign_enum(): void
    {
        $expected = [
            'Surface: ' => count(CampaignSurface::cases()),
            'Placement: ' => count(CampaignPlacement::cases()),
            'Trigger: ' => count(CampaignTriggerType::cases()),
            'Frequency: ' => count(CampaignFrequencyPolicy::cases()),
            'CTA: ' => count(CampaignCtaType::cases()),
            'Status: ' => count(CampaignStatus::cases()),
        ];

        foreach ($expected as $prefix => $count) {
            $this->assertSame(
                $count,
                $this->countOptionsWithPrefix('administrator', 'campaigns', $prefix),
                "Campaign options for \"{$prefix}\" do not match the enum.",
            );
        }
    }

    public function test_loyalty_reward_options_cover_every_reward_type(): void
    {
        $module = (new DocumentationCatalog)->findModule('administrator', 'loyalty-rewards');

        $this->assertCount(count(LoyaltyRewardType::cases()), $module['options']);
    }

    public function test_promotions_module_rules_out_unsupported_conditions(): void
    {
        $module = (new DocumentationCatalog)->findModule('administrator', 'promotions');
        $notes = mb_strtolower(implode(' ', $module['notes']));

        $this->assertStringContainsString('no minimum quantity condition', $notes);
        $this->assertStringContainsString('no free-item promotion type', $notes);
    }

    public function test_waiter_documentation_explains_multiple_waiters_on_one_session(): void
    {
        $module = (new DocumentationCatalog)->findModule('waiter', 'multi-waiter-session');

        $this->assertNotNull($module);
        $this->assertStringContainsString('Table 12', $module['examples'][0]['body']);
    }

    protected function countOptionsWithPrefix(string $role, string $slug, string $prefix): int
    {
        $module = (new DocumentationCatalog)->findModule($role, $slug);

        return count(array_filter(
            $module['options'],
            fn (array $option): bool => str_starts_with($option['name'], $prefix),
        ));
    }
}
