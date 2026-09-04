<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttributionAnalyticsAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_view_recommendation_and_campaign_analytics(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.reports.recommendations.index'))
            ->assertOk()
            ->assertSee('Recommendation Performance')
            ->assertSee('Impressions');

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.reports.campaigns.index'))
            ->assertOk()
            ->assertSee('Campaign Performance')
            ->assertSee('Unique actors');
    }

    public function test_barista_cannot_view_attribution_analytics(): void
    {
        $barista = User::factory()->barista()->create();

        $this->actingAs($barista, 'admin')
            ->get(route('administrator.reports.recommendations.index'))
            ->assertForbidden();

        $this->actingAs($barista, 'admin')
            ->get(route('administrator.reports.campaigns.index'))
            ->assertForbidden();
    }
}
