<?php

namespace Tests\Feature;

use App\Enums\OperationalNotificationPriority;
use App\Enums\UserRole;
use App\Events\Realtime\OperationalNotificationBroadcasted;
use App\Models\User;
use App\Services\OperationalNotification\OperationalNotificationServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class OperationalNotificationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_and_broadcast_persists_user_specific_recipients_and_minimal_payload(): void
    {
        Event::fake([OperationalNotificationBroadcasted::class]);

        $waiter = User::factory()->waiter()->create();
        $service = app(OperationalNotificationServiceInterface::class);

        $notification = $service->createAndBroadcast(
            type: 'ops.probe',
            category: 'system',
            title: 'Probe title',
            message: 'Probe message',
            audience: [$waiter],
            priority: OperationalNotificationPriority::High,
            actionRequired: true,
            actionCode: 'review_probe',
            actionUrl: '/waiter',
            actor: $waiter,
            metadata: ['source' => 'test'],
        );

        $this->assertDatabaseHas('operational_notifications', [
            'id' => $notification->id,
            'type' => 'ops.probe',
            'category' => 'system',
            'action_required' => 1,
            'action_code' => 'review_probe',
        ]);

        $this->assertDatabaseHas('operational_notification_recipients', [
            'operational_notification_id' => $notification->id,
            'user_id' => $waiter->id,
            'role' => UserRole::Waiter->value,
        ]);

        Event::assertDispatched(OperationalNotificationBroadcasted::class, function (OperationalNotificationBroadcasted $event) use ($waiter, $notification): bool {
            $payload = $event->broadcastWith();
            $channels = collect($event->broadcastOn())->map(fn ($channel) => (string) $channel)->all();

            $this->assertSame('operational.notification', $event->broadcastAs());
            $this->assertContains('private-user.'.$waiter->id, $channels);
            $this->assertSame($notification->id, $payload['id']);
            $this->assertArrayHasKey('recipient_id', $payload);
            $this->assertSame('ops.probe', $payload['type']);
            $this->assertSame('system', $payload['category']);
            $this->assertSame('high', $payload['priority']);
            $this->assertSame('Probe title', $payload['title']);
            $this->assertTrue($payload['action_required']);
            $this->assertSame('review_probe', $payload['action_code']);
            $this->assertArrayNotHasKey('metadata', $payload);
            $this->assertArrayNotHasKey('actor_id', $payload);

            return true;
        });

        $recipient = $notification->recipients()->first();
        $this->assertNotNull($recipient?->broadcast_at);
    }

    public function test_role_audience_expands_to_active_users_only(): void
    {
        Event::fake([OperationalNotificationBroadcasted::class]);

        $active = User::factory()->barista()->create(['is_active' => true]);
        $inactive = User::factory()->barista()->create(['is_active' => false]);
        User::factory()->chef()->create(['is_active' => true]);

        $notification = app(OperationalNotificationServiceInterface::class)->createAndBroadcast(
            type: 'ops.role',
            category: 'preparation',
            title: 'Role alert',
            message: 'Barista team',
            audience: [UserRole::Barista],
            broadcast: false,
        );

        $userIds = $notification->recipients->pluck('user_id')->all();

        $this->assertContains($active->id, $userIds);
        $this->assertNotContains($inactive->id, $userIds);
        $this->assertCount(1, $userIds);
        Event::assertNotDispatched(OperationalNotificationBroadcasted::class);
        $this->assertNull($notification->recipients->first()?->broadcast_at);
    }

    public function test_ack_endpoints_are_idempotent_and_set_first_timestamp_only(): void
    {
        $user = User::factory()->waiter()->create();
        Sanctum::actingAs($user);

        $notification = app(OperationalNotificationServiceInterface::class)->createAndBroadcast(
            type: 'ops.ack',
            category: 'system',
            title: 'Ack me',
            message: 'Please ack',
            audience: [$user],
            actionRequired: true,
            actionCode: 'ack',
            broadcast: false,
        );

        $recipient = $notification->recipients()->firstOrFail();

        $this->travelTo(now()->addSeconds(10));
        $this->postJson('/api/v1/notifications/'.$recipient->id.'/delivered')->assertOk();
        $firstDelivered = $recipient->fresh()->delivered_at;

        $this->travelTo(now()->addSeconds(30));
        $this->postJson('/api/v1/notifications/'.$recipient->id.'/delivered')->assertOk();
        $this->assertTrue($firstDelivered->equalTo($recipient->fresh()->delivered_at));

        $this->postJson('/api/v1/notifications/'.$recipient->id.'/seen')->assertOk();
        $firstSeen = $recipient->fresh()->first_seen_at;
        $this->travelTo(now()->addMinute());
        $this->postJson('/api/v1/notifications/'.$recipient->id.'/seen')->assertOk();
        $this->assertTrue($firstSeen->equalTo($recipient->fresh()->first_seen_at));

        $this->postJson('/api/v1/notifications/'.$recipient->id.'/read')->assertOk();
        $firstRead = $recipient->fresh()->read_at;
        $this->travelTo(now()->addMinute());
        $this->postJson('/api/v1/notifications/'.$recipient->id.'/read')->assertOk();
        $this->assertTrue($firstRead->equalTo($recipient->fresh()->read_at));

        $this->postJson('/api/v1/notifications/'.$recipient->id.'/acknowledge')->assertOk();
        $this->assertNotNull($recipient->fresh()->acknowledged_at);
        $firstAck = $recipient->fresh()->acknowledged_at;
        $this->travelTo(now()->addMinute());
        $this->postJson('/api/v1/notifications/'.$recipient->id.'/acknowledge')->assertOk();
        $this->assertTrue($firstAck->equalTo($recipient->fresh()->acknowledged_at));
    }

    public function test_resolve_shared_notification_preserves_recipient_history(): void
    {
        $baristaA = User::factory()->barista()->create();
        $baristaB = User::factory()->barista()->create();
        $service = app(OperationalNotificationServiceInterface::class);

        $notification = $service->createAndBroadcast(
            type: 'ops.shared',
            category: 'preparation',
            title: 'Shared',
            message: 'Team action',
            audience: [UserRole::Barista],
            actionRequired: true,
            actionCode: 'claim',
            broadcast: false,
        );

        $recipientA = $notification->recipients()->where('user_id', $baristaA->id)->firstOrFail();
        $recipientB = $notification->recipients()->where('user_id', $baristaB->id)->firstOrFail();

        $service->markSeen($recipientA);
        $service->acknowledge($recipientA);
        $service->markActionStarted($recipientA);
        $service->markActionCompleted($recipientA);

        $resolved = $service->resolve($notification, $baristaA, 'completed');

        $this->assertNotNull($resolved->resolved_at);
        $this->assertSame('completed', $resolved->resolution_action);
        $this->assertSame($baristaA->id, $resolved->resolved_by_id);

        $recipientA = $recipientA->fresh();
        $recipientB = $recipientB->fresh();

        $this->assertNotNull($recipientA->acknowledged_at);
        $this->assertNotNull($recipientA->action_completed_at);
        $this->assertNull($recipientB->acknowledged_at);
        $this->assertNull($recipientB->first_seen_at);
        $this->assertTrue($resolved->fresh()->isResolved());
    }

    public function test_websocket_failure_does_not_break_persistence(): void
    {
        Event::listen(OperationalNotificationBroadcasted::class, function (): void {
            throw new RuntimeException('websocket unavailable');
        });

        $user = User::factory()->operator()->create();

        $notification = app(OperationalNotificationServiceInterface::class)->createAndBroadcast(
            type: 'ops.ws-fail',
            category: 'system',
            title: 'Still persist',
            message: 'Even if broadcast throws',
            audience: [$user],
        );

        $this->assertDatabaseHas('operational_notifications', [
            'id' => $notification->id,
            'type' => 'ops.ws-fail',
        ]);
        $this->assertDatabaseCount('operational_notification_recipients', 1);
        $this->assertNull($notification->fresh('recipients')->recipients->first()?->broadcast_at);
    }

    public function test_authorization_matrix_for_recipient_lifecycle(): void
    {
        $owner = User::factory()->owner()->create();
        $otherStaff = User::factory()->barista()->create();
        $customer = User::factory()->create(['role' => UserRole::Customer]);

        $staffNotification = app(OperationalNotificationServiceInterface::class)->createAndBroadcast(
            type: 'ops.staff',
            category: 'ops',
            title: 'Staff only',
            message: 'Owner alert',
            audience: [$owner],
            actionRequired: true,
            actionCode: 'review',
            broadcast: false,
        );
        $staffRecipient = $staffNotification->recipients()->firstOrFail();

        $this->postJson('/api/v1/notifications/'.$staffRecipient->id.'/delivered')->assertUnauthorized();

        Sanctum::actingAs($otherStaff);
        $this->postJson('/api/v1/notifications/'.$staffRecipient->id.'/delivered')->assertNotFound();
        $this->getJson('/api/v1/notifications')->assertOk()->assertJsonCount(0, 'data');

        Sanctum::actingAs($customer);
        $this->postJson('/api/v1/notifications/'.$staffRecipient->id.'/seen')->assertNotFound();
        $this->getJson('/api/v1/notifications')->assertOk()->assertJsonCount(0, 'data');

        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('meta.unread_count', 1)
            ->assertJsonPath('data.0.recipient_id', $staffRecipient->id)
            ->assertJsonMissingPath('data.0.metadata');

        $this->getJson('/api/v1/notifications/action-required')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->postJson('/api/v1/notifications/'.$staffRecipient->id.'/read')->assertOk();
    }

    public function test_admin_guard_session_can_access_notification_api(): void
    {
        $operator = User::factory()->operator()->create();
        $notification = app(OperationalNotificationServiceInterface::class)->createAndBroadcast(
            type: 'ops.admin-session',
            category: 'system',
            title: 'Admin session',
            message: 'From blade',
            audience: [$operator],
            broadcast: false,
        );
        $recipient = $notification->recipients()->firstOrFail();

        $this->actingAs($operator, 'admin')
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.recipient_id', $recipient->id);
    }

    public function test_computed_response_metrics_handle_null_stages(): void
    {
        $this->travelTo(now()->startOfMinute());

        $user = User::factory()->chef()->create();
        $service = app(OperationalNotificationServiceInterface::class);

        $notification = $service->createAndBroadcast(
            type: 'ops.metrics',
            category: 'system',
            title: 'Metrics',
            message: 'Delays',
            audience: [$user],
            actionRequired: true,
            actionCode: 'go',
            broadcast: false,
        );

        $recipient = $notification->recipients()->firstOrFail();
        $metrics = $service->metricsForRecipient($recipient);

        $this->assertNull($metrics['delivery_delay_seconds']);
        $this->assertNull($metrics['first_seen_delay_seconds']);
        $this->assertNull($metrics['acknowledge_delay_seconds']);
        $this->assertNull($metrics['action_start_delay_seconds']);
        $this->assertNull($metrics['action_completion_delay_seconds']);
        $this->assertNull($metrics['resolution_delay_seconds']);

        $this->travelTo(now()->addSeconds(25));
        $service->markDelivered($recipient);
        $this->travelTo(now()->addSeconds(15));
        $service->markSeen($recipient->fresh());
        $this->travelTo(now()->addSeconds(20));
        $service->acknowledge($recipient->fresh());
        $this->travelTo(now()->addSeconds(10));
        $service->markActionStarted($recipient->fresh());
        $this->travelTo(now()->addSeconds(30));
        $service->markActionCompleted($recipient->fresh());
        $this->travelTo(now()->addSeconds(5));
        $service->resolve($notification->fresh(), $user, 'done');

        $fresh = $recipient->fresh(['notification']);
        $metrics = $service->metricsForRecipient($fresh);

        $this->assertSame(25, $metrics['delivery_delay_seconds']);
        $this->assertSame(40, $metrics['first_seen_delay_seconds']);
        $this->assertSame(60, $metrics['acknowledge_delay_seconds']);
        $this->assertSame(70, $metrics['action_start_delay_seconds']);
        $this->assertSame(100, $metrics['action_completion_delay_seconds']);
        $this->assertSame(105, $metrics['resolution_delay_seconds']);
        $this->assertSame(25, $fresh->deliveryDelaySeconds());
    }

    public function test_list_endpoints_do_not_leak_other_users_or_role_data(): void
    {
        $waiter = User::factory()->waiter()->create();
        $barista = User::factory()->barista()->create();

        app(OperationalNotificationServiceInterface::class)->createAndBroadcast(
            type: 'ops.waiter',
            category: 'dining',
            title: 'Waiter note',
            message: 'For waiter',
            audience: [$waiter],
            broadcast: false,
        );

        app(OperationalNotificationServiceInterface::class)->createAndBroadcast(
            type: 'ops.barista',
            category: 'preparation',
            title: 'Barista note',
            message: 'For barista',
            audience: [$barista],
            broadcast: false,
        );

        Sanctum::actingAs($waiter);
        $response = $this->getJson('/api/v1/notifications')->assertOk();
        $ids = collect($response->json('data'))->pluck('type')->all();

        $this->assertSame(['ops.waiter'], $ids);
        $this->assertStringNotContainsString('ops.barista', (string) $response->getContent());
        $this->assertArrayNotHasKey('role', $response->json('data.0') ?? []);
    }

    public function test_pwa_and_blade_notification_client_foundation_exists(): void
    {
        $api = File::get(base_path('customer-pwa/src/api/notifications.ts'));
        $store = File::get(base_path('customer-pwa/src/stores/notificationStore.ts'));
        $bladeClient = File::get(base_path('resources/js/notifications.js'));
        $realtime = File::get(base_path('resources/js/realtime.js'));

        $this->assertStringContainsString('fetchNotifications', $api);
        $this->assertStringContainsString('markNotificationDelivered', $api);
        $this->assertStringContainsString('unreadCount', $store);
        $this->assertStringContainsString('actionRequiredCount', $store);
        $this->assertStringContainsString('acknowledge', $store);
        $this->assertStringContainsString('__COFFEE_NOTIFICATIONS__', $bladeClient);
        $this->assertStringContainsString('operational.notification', $realtime);
        $this->assertStringContainsString('resources/js/notifications.js', File::get(base_path('resources/views/internal/partials/realtime-bootstrap.blade.php')));
    }
}
