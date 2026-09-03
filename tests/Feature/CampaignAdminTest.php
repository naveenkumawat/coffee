<?php

namespace Tests\Feature;

use App\Enums\CampaignCtaType;
use App\Enums\CampaignFrequencyPolicy;
use App\Enums\CampaignStatus;
use App\Enums\CampaignSurface;
use App\Enums\CampaignTriggerType;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_activate_pause_and_archive_campaign(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager, 'admin')
            ->post(route('administrator.campaigns.store'), [
                'name' => 'Weekend Welcome',
                'internal_label' => 'weekend-welcome',
                'status' => CampaignStatus::Draft->value,
                'surface' => CampaignSurface::Popup->value,
                'title' => 'Welcome back',
                'message' => 'Try something new this weekend.',
                'cta_label' => 'Browse menu',
                'cta_type' => CampaignCtaType::InternalPage->value,
                'cta_internal_path' => '/menu',
                'priority' => 25,
                'frequency_policy' => CampaignFrequencyPolicy::OncePerSession->value,
                'placement_rules' => json_encode([
                    'placements' => ['home'],
                    'category_ids' => [],
                    'product_ids' => [],
                    'product_tag_ids' => [],
                ]),
                'targeting_rules' => json_encode([
                    'all' => [
                        ['type' => 'identity', 'op' => 'eq', 'value' => 'everyone'],
                    ],
                    'any' => [],
                    'exclude' => [],
                ]),
                'trigger_rules' => json_encode([
                    'type' => CampaignTriggerType::Delay->value,
                    'delay_ms' => 1500,
                    'scroll_percent' => null,
                    'product_view_count' => null,
                ]),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $campaign = Campaign::query()->where('name', 'Weekend Welcome')->first();
        $this->assertNotNull($campaign);
        $this->assertSame(CampaignStatus::Draft, $campaign->status);
        $this->assertSame('/menu', $campaign->cta_internal_path);

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.campaigns.index'))
            ->assertOk()
            ->assertSee('Weekend Welcome');

        $this->actingAs($manager, 'admin')
            ->patch(route('administrator.campaigns.status', [$campaign, 'active']))
            ->assertRedirect(route('administrator.campaigns.index'));

        $this->assertSame(CampaignStatus::Active, $campaign->fresh()->status);

        $this->actingAs($manager, 'admin')
            ->patch(route('administrator.campaigns.status', [$campaign, 'paused']))
            ->assertRedirect(route('administrator.campaigns.index'));

        $this->assertSame(CampaignStatus::Paused, $campaign->fresh()->status);

        $this->actingAs($manager, 'admin')
            ->delete(route('administrator.campaigns.destroy', $campaign))
            ->assertRedirect(route('administrator.campaigns.index'));

        $this->assertSoftDeleted($campaign);
    }

    public function test_invalid_targeting_rule_is_rejected(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager, 'admin')
            ->post(route('administrator.campaigns.store'), [
                'name' => 'Bad Rules',
                'status' => CampaignStatus::Draft->value,
                'surface' => CampaignSurface::Popup->value,
                'title' => 'Bad',
                'cta_type' => CampaignCtaType::Close->value,
                'frequency_policy' => CampaignFrequencyPolicy::EverySession->value,
                'placement_rules' => json_encode([
                    'placements' => ['home'],
                    'category_ids' => [],
                    'product_ids' => [],
                    'product_tag_ids' => [],
                ]),
                'targeting_rules' => json_encode([
                    'all' => [
                        ['type' => 'hack_me', 'op' => 'eq', 'value' => 1],
                    ],
                    'any' => [],
                    'exclude' => [],
                ]),
                'trigger_rules' => json_encode([
                    'type' => CampaignTriggerType::Immediate->value,
                ]),
            ])
            ->assertSessionHasErrors();
    }
}
