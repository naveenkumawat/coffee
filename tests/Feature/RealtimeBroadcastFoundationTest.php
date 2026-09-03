<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Events\Realtime\RealtimeConnectionProbe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RealtimeBroadcastFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_customer_can_authorize_own_user_channel(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);

        $this->actingAs($customer, 'web')
            ->postJson('/broadcasting/auth', [
                'channel_name' => 'private-user.'.$customer->id,
                'socket_id' => '1234.5678',
            ])
            ->assertOk();
    }

    public function test_customer_cannot_authorize_another_users_channel(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $other = User::factory()->create(['role' => UserRole::Customer]);

        $this->actingAs($customer, 'web')
            ->postJson('/broadcasting/auth', [
                'channel_name' => 'private-user.'.$other->id,
                'socket_id' => '1234.5678',
            ])
            ->assertForbidden();
    }

    public function test_customer_cannot_authorize_staff_role_channels(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);

        foreach (['administrator', 'operator', 'barista', 'chef', 'waiter'] as $role) {
            $this->actingAs($customer, 'web')
                ->postJson('/broadcasting/auth', [
                    'channel_name' => 'private-role.'.$role,
                    'socket_id' => '1234.5678',
                ])
                ->assertForbidden();
        }
    }

    public function test_staff_roles_authorize_matching_role_channels_only(): void
    {
        $cases = [
            [UserRole::Owner, 'administrator', true],
            [UserRole::Manager, 'administrator', true],
            [UserRole::Operator, 'operator', true],
            [UserRole::Barista, 'barista', true],
            [UserRole::Chef, 'chef', true],
            [UserRole::Waiter, 'waiter', true],
            [UserRole::Barista, 'chef', false],
            [UserRole::Waiter, 'administrator', false],
            [UserRole::Customer, 'waiter', false],
        ];

        foreach ($cases as [$role, $channelRole, $allowed]) {
            $user = User::factory()->create(['role' => $role]);
            $guard = $role === UserRole::Customer ? 'web' : 'admin';

            $response = $this->actingAs($user, $guard)
                ->postJson('/broadcasting/auth', [
                    'channel_name' => 'private-role.'.$channelRole,
                    'socket_id' => '1234.5678',
                ]);

            if ($allowed) {
                $response->assertOk();
            } else {
                $response->assertForbidden();
            }
        }
    }

    public function test_guest_cannot_authorize_private_channels(): void
    {
        $this->postJson('/broadcasting/auth', [
            'channel_name' => 'private-realtime.probe',
            'socket_id' => '1234.5678',
        ])->assertUnauthorized();
    }

    public function test_probe_event_broadcasts_minimal_payload_on_private_channels(): void
    {
        Event::fake([RealtimeConnectionProbe::class]);

        $user = User::factory()->create(['role' => UserRole::Customer]);
        $event = RealtimeConnectionProbe::forUser($user, 'probe-test-1');

        event($event);

        Event::assertDispatched(RealtimeConnectionProbe::class, function (RealtimeConnectionProbe $dispatched) use ($user): bool {
            $channels = collect($dispatched->broadcastOn())->map(fn ($channel) => (string) $channel)->all();

            $this->assertContains('private-user.'.$user->id, $channels);
            $this->assertContains('private-realtime.probe', $channels);
            $this->assertSame('realtime.probe', $dispatched->broadcastAs());
            $this->assertSame([
                'probe_id' => 'probe-test-1',
                'user_id' => $user->id,
            ], $dispatched->broadcastWith());

            return true;
        });
    }

    public function test_pwa_and_blade_share_single_realtime_bootstrap_surfaces(): void
    {
        $layout = File::get(base_path('customer-pwa/src/layouts/AppLayout.tsx'));
        $connection = File::get(base_path('customer-pwa/src/realtime/RealtimeConnection.ts'));
        $blade = File::get(base_path('resources/views/internal/partials/realtime-bootstrap.blade.php'));
        $channels = File::get(base_path('routes/channels.php'));

        $this->assertStringContainsString('useRealtimeBootstrap', $layout);
        $this->assertStringContainsString('RealtimeStatusIndicator', $layout);
        $this->assertStringContainsString('connectGeneration', $connection);
        $this->assertStringContainsString('disconnect', $connection);
        $this->assertStringContainsString('resources/js/realtime.js', $blade);
        $this->assertStringContainsString("Broadcast::channel('user.{id}'", $channels);
        $this->assertStringContainsString("Broadcast::channel('role.administrator'", $channels);
        $this->assertStringContainsString("Broadcast::channel('realtime.probe'", $channels);
    }
}
