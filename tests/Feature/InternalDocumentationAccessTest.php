<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalDocumentationAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_role_can_open_its_documentation_index_and_a_scoped_module(): void
    {
        $cases = [
            ['factory' => 'owner', 'guardPanel' => 'administrator', 'module' => 'promotions'],
            ['factory' => 'operator', 'guardPanel' => 'operator', 'module' => 'orders'],
            ['factory' => 'waiter', 'guardPanel' => 'waiter', 'module' => 'tables'],
            ['factory' => 'chef', 'guardPanel' => 'chef', 'module' => 'kitchen-queue'],
            ['factory' => 'barista', 'guardPanel' => 'barista', 'module' => 'bar-queue'],
        ];

        foreach ($cases as $case) {
            $user = User::factory()->{$case['factory']}()->create();

            $this->actingAs($user, 'admin')
                ->get(route($case['guardPanel'].'.documentation.index'))
                ->assertOk()
                ->assertSee('Documentation');

            $this->actingAs($user, 'admin')
                ->get(route($case['guardPanel'].'.documentation.show', $case['module']))
                ->assertOk();
        }
    }

    public function test_roles_cannot_open_another_panel_documentation(): void
    {
        $waiter = User::factory()->waiter()->create();

        $this->actingAs($waiter, 'admin')
            ->get(route('administrator.documentation.index'))
            ->assertForbidden();

        $this->actingAs($waiter, 'admin')
            ->get(route('operator.documentation.index'))
            ->assertForbidden();
    }

    public function test_waiter_cannot_open_administrator_marketing_module_via_waiter_route(): void
    {
        $waiter = User::factory()->waiter()->create();

        $this->actingAs($waiter, 'admin')
            ->get(route('waiter.documentation.show', 'promotions'))
            ->assertNotFound();
    }

    public function test_administrator_documentation_includes_marketing_and_loyalty_modules(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner, 'admin')
            ->get(route('administrator.documentation.index'))
            ->assertOk()
            ->assertSee('Offers & Promotions')
            ->assertSee('Audience Segments')
            ->assertSee('Campaigns');

        $this->actingAs($owner, 'admin')
            ->get(route('administrator.documentation.show', 'audience-segments'))
            ->assertOk()
            ->assertSee('Available options');
    }

    public function test_campaign_form_exposes_template_controls(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.campaigns.create'))
            ->assertOk()
            ->assertSee('Who should see this campaign')
            ->assertSee('Use template')
            ->assertSee('Advanced JSON');
    }

    public function test_segment_form_exposes_template_controls(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.segments.create'))
            ->assertOk()
            ->assertSee('Who belongs in this audience')
            ->assertSee('How targeting rules work')
            ->assertSee('Use template');
    }
}
