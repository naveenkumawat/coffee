<?php

namespace Tests\Feature;

use App\Enums\OperationalNotificationType;
use App\Models\User;
use App\Services\OperationalNotification\OperationalNotificationServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OperationalNotificationReminderEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_reminded_increments_atomically_for_own_actionable_recipient(): void
    {
        $operator = User::factory()->operator()->create();
        Sanctum::actingAs($operator);

        $notification = app(OperationalNotificationServiceInterface::class)->createAndBroadcast(
            type: OperationalNotificationType::OrderRequiresAcceptance->value,
            category: 'order',
            title: 'Needs acceptance',
            message: 'Please accept',
            audience: [$operator],
            actionRequired: true,
            actionCode: 'accept_order',
            broadcast: false,
        );

        $recipient = $notification->recipients()->firstOrFail();
        $this->assertSame(0, (int) $recipient->reminder_count);

        $this->postJson('/api/v1/notifications/'.$recipient->id.'/reminded')
            ->assertOk()
            ->assertJsonPath('data.reminder_count', 1);

        $this->postJson('/api/v1/notifications/'.$recipient->id.'/reminded')
            ->assertOk()
            ->assertJsonPath('data.reminder_count', 2);

        $this->assertNotNull($recipient->fresh()->last_reminded_at);
    }

    public function test_reminded_is_noop_when_resolved_or_non_eligible(): void
    {
        $operator = User::factory()->operator()->create();
        Sanctum::actingAs($operator);
        $service = app(OperationalNotificationServiceInterface::class);

        $actionable = $service->createAndBroadcast(
            type: OperationalNotificationType::OrderRequiresAcceptance->value,
            category: 'order',
            title: 'Needs acceptance',
            message: 'Please accept',
            audience: [$operator],
            actionRequired: true,
            broadcast: false,
        );
        $recipient = $actionable->recipients()->firstOrFail();
        $service->resolve($actionable, $operator, 'accepted');

        $this->postJson('/api/v1/notifications/'.$recipient->id.'/reminded')
            ->assertOk()
            ->assertJsonPath('data.reminder_count', 0);

        $info = $service->createAndBroadcast(
            type: OperationalNotificationType::OrderCancelled->value,
            category: 'order',
            title: 'Cancelled',
            message: 'Informational',
            audience: [$operator],
            actionRequired: false,
            broadcast: false,
        );
        $infoRecipient = $info->recipients()->firstOrFail();

        $this->postJson('/api/v1/notifications/'.$infoRecipient->id.'/reminded')
            ->assertOk()
            ->assertJsonPath('data.reminder_count', 0);
    }

    public function test_reminded_authorization_matrix(): void
    {
        $owner = User::factory()->owner()->create();
        $other = User::factory()->barista()->create();

        $notification = app(OperationalNotificationServiceInterface::class)->createAndBroadcast(
            type: OperationalNotificationType::OrderRequiresAttention->value,
            category: 'order',
            title: 'Cash order',
            message: 'Attention',
            audience: [$owner],
            actionRequired: true,
            broadcast: false,
        );
        $recipient = $notification->recipients()->firstOrFail();

        $this->postJson('/api/v1/notifications/'.$recipient->id.'/reminded')->assertUnauthorized();

        Sanctum::actingAs($other);
        $this->postJson('/api/v1/notifications/'.$recipient->id.'/reminded')->assertNotFound();

        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/notifications/'.$recipient->id.'/reminded')->assertOk();
    }

    public function test_blade_notification_client_foundation_files_exist(): void
    {
        $this->assertFileExists(base_path('resources/js/notifications/bootstrap.js'));
        $this->assertFileExists(base_path('resources/js/notifications/reminder.js'));
        $this->assertFileExists(base_path('resources/js/notifications/sound.js'));
        $this->assertFileExists(base_path('resources/js/notifications/tabLeader.js'));
        $this->assertFileExists(base_path('resources/sounds/notification-chime.wav'));
        $this->assertStringContainsString('coffee-ops-bell', file_get_contents(base_path('resources/views/internal/includes/partials/header.blade.php')));
        $this->assertStringContainsString('coffee-ops-drawer', file_get_contents(base_path('resources/views/internal/partials/operational-notification-ui.blade.php')));
        $this->assertStringContainsString('REMINDER_INTERVAL_MS', file_get_contents(base_path('resources/js/notifications/config.js')));
    }
}
