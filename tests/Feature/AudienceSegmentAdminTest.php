<?php

namespace Tests\Feature;

use App\Enums\AudienceSegmentActor;
use App\Enums\AudienceSegmentStatus;
use App\Models\AudienceSegment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AudienceSegmentAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_activate_pause_preview_and_archive_segment(): void
    {
        $manager = User::factory()->manager()->create();
        $customer = User::factory()->customer()->create();

        $this->actingAs($manager, 'admin')
            ->post(route('administrator.segments.store'), [
                'name' => 'Repeat Buyers',
                'slug' => 'repeat-buyers',
                'description' => 'Customers with at least two completed orders',
                'status' => AudienceSegmentStatus::Draft->value,
                'actor_scope' => AudienceSegmentActor::Customer->value,
                'rules' => json_encode([
                    'all' => [
                        ['type' => 'completed_orders', 'op' => 'gte', 'value' => 2],
                    ],
                    'any' => [],
                    'exclude' => [],
                ]),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $segment = AudienceSegment::query()->where('name', 'Repeat Buyers')->first();
        $this->assertNotNull($segment);
        $this->assertSame(AudienceSegmentStatus::Draft, $segment->status);
        $this->assertNotEmpty($segment->stable_key);

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.segments.index'))
            ->assertOk()
            ->assertSee('Repeat Buyers');

        $this->actingAs($manager, 'admin')
            ->patch(route('administrator.segments.status', [$segment, 'active']))
            ->assertRedirect(route('administrator.segments.index'));

        $this->assertSame(AudienceSegmentStatus::Active, $segment->fresh()->status);

        $this->actingAs($manager, 'admin')
            ->from(route('administrator.segments.edit', $segment))
            ->post(route('administrator.segments.preview', $segment), [
                'customer_id' => $customer->id,
            ])
            ->assertRedirect(route('administrator.segments.edit', $segment))
            ->assertSessionHas('status');

        $this->actingAs($manager, 'admin')
            ->patch(route('administrator.segments.status', [$segment, 'paused']))
            ->assertRedirect(route('administrator.segments.index'));

        $this->assertSame(AudienceSegmentStatus::Paused, $segment->fresh()->status);

        $this->actingAs($manager, 'admin')
            ->delete(route('administrator.segments.destroy', $segment))
            ->assertRedirect(route('administrator.segments.index'));

        $this->assertSoftDeleted($segment);
    }

    public function test_invalid_rules_and_unauthorized_access_are_rejected(): void
    {
        $manager = User::factory()->manager()->create();
        $barista = User::factory()->barista()->create();

        $this->actingAs($manager, 'admin')
            ->post(route('administrator.segments.store'), [
                'name' => 'Bad Segment',
                'status' => AudienceSegmentStatus::Draft->value,
                'actor_scope' => AudienceSegmentActor::Both->value,
                'rules' => json_encode([
                    'all' => [
                        ['type' => 'ethnicity', 'op' => 'eq', 'value' => 'x'],
                    ],
                    'any' => [],
                    'exclude' => [],
                ]),
            ])
            ->assertSessionHasErrors();

        $this->actingAs($barista, 'admin')
            ->get(route('administrator.segments.index'))
            ->assertForbidden();
    }
}
