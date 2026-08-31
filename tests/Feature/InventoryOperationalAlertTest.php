<?php

namespace Tests\Feature;

use App\Enums\IngredientUnit;
use App\Enums\InventoryTransactionType;
use App\Enums\StaffNotificationAudience;
use App\Enums\StaffNotificationChannel;
use App\Enums\StaffNotificationType;
use App\Models\Ingredient;
use App\Models\InventoryRefillRequest;
use App\Models\StaffNotificationLog;
use App\Models\User;
use App\Notifications\StaffOperationalNotification;
use App\Services\Inventory\InventoryRefillRequestServiceInterface;
use App\Services\Inventory\InventoryServiceInterface;
use App\Services\Notification\StaffNotificationContext;
use App\Services\Notification\StaffNotificationDispatcherInterface;
use App\Transfers\Inventory\InventoryRefillRequestTransferInterface;
use App\Transfers\Inventory\InventoryTransactionTransferInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class InventoryOperationalAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_healthy_to_low_notifies_administrators_once_and_low_to_lower_does_not_duplicate(): void
    {
        Notification::fake();

        $admin = User::factory()->owner()->create();
        $inactiveAdmin = User::factory()->manager()->inactive()->create();
        $barista = User::factory()->barista()->create();
        $ingredient = $this->makeTrackedIngredient(current: '50.000', reorder: '30.000', minimum: '10.000');

        $this->recordMovement($ingredient, InventoryTransactionType::ManualReduction, '25.000', $admin);
        $this->assertSame('25.000', $ingredient->fresh()->current_stock);
        Notification::assertNotSentTo($inactiveAdmin, StaffOperationalNotification::class);
        Notification::assertNotSentTo($barista, StaffOperationalNotification::class);

        $this->assertSame(
            1,
            StaffNotificationLog::query()
                ->where('type', StaffNotificationType::IngredientLowStock)
                ->where('channel', StaffNotificationChannel::Database)
                ->where('status', 'sent')
                ->count(),
        );
        $this->assertSame(
            0,
            StaffNotificationLog::query()
                ->where('type', StaffNotificationType::IngredientLowStock)
                ->where('channel', StaffNotificationChannel::Email)
                ->count(),
        );

        Notification::fake();
        $this->recordMovement($ingredient, InventoryTransactionType::ManualReduction, '5.000', $admin);

        Notification::assertNothingSent();
    }

    public function test_low_to_out_sends_critical_alert_and_out_to_out_does_not_duplicate(): void
    {
        Notification::fake();

        $admin = User::factory()->owner()->create();
        $barista = User::factory()->barista()->create();
        $ingredient = $this->makeTrackedIngredient(current: '20.000', reorder: '30.000', minimum: '10.000');

        $this->recordMovement($ingredient, InventoryTransactionType::ManualReduction, '20.000', $admin);

        Notification::assertSentTo($admin, StaffOperationalNotification::class, function (StaffOperationalNotification $notification) use ($admin): bool {
            if ($notification->type !== StaffNotificationType::IngredientOutOfStock) {
                return false;
            }

            $data = $notification->toDatabase($admin);
            $mail = $notification->toMail($admin)->render();

            $this->assertSame('critical', $data['severity']);
            $this->assertStringContainsString('out of stock', strtolower($data['title']));
            $this->assertStringContainsString(route('administrator.inventory.index', ['stock_status' => 'out_of_stock']), $data['url']);
            $this->assertStringNotContainsString('purchase_cost', strtolower($mail));
            $this->assertStringNotContainsString('cost_per_unit', strtolower($mail));
            $this->assertStringNotContainsString('gross profit', strtolower($mail));

            return true;
        });
        Notification::assertSentTo($barista, StaffOperationalNotification::class, function (StaffOperationalNotification $notification) use ($barista): bool {
            $data = $notification->toDatabase($barista);

            return $notification->type === StaffNotificationType::IngredientOutOfStock
                && $data['total_amount'] === null
                && $data['customer_name'] === null
                && str_contains($data['url'], route('barista.inventory.index', ['stock_status' => 'out_of_stock']));
        });

        $this->assertTrue(
            StaffNotificationLog::query()
                ->where('type', StaffNotificationType::IngredientOutOfStock)
                ->where('channel', StaffNotificationChannel::Email)
                ->where('user_id', $admin->id)
                ->where('status', 'sent')
                ->exists(),
        );

        Notification::fake();
        $this->recordMovement($ingredient, InventoryTransactionType::ManualAdjustment, '0.000', $admin);
        Notification::assertNothingSent();
    }

    public function test_out_to_healthy_sends_recovery_and_later_low_is_a_new_episode(): void
    {
        Notification::fake();

        $admin = User::factory()->owner()->create();
        $ingredient = $this->makeTrackedIngredient(current: '0.000', reorder: '30.000', minimum: '10.000');

        $this->recordMovement($ingredient, InventoryTransactionType::StockAdded, '80.000', $admin);

        Notification::assertSentTo(
            $admin,
            StaffOperationalNotification::class,
            fn (StaffOperationalNotification $notification): bool => $notification->type === StaffNotificationType::IngredientStockRestored,
        );

        Notification::fake();
        $this->recordMovement($ingredient, InventoryTransactionType::ManualReduction, '60.000', $admin);

        Notification::assertSentTo(
            $admin,
            StaffOperationalNotification::class,
            fn (StaffOperationalNotification $notification): bool => $notification->type === StaffNotificationType::IngredientLowStock,
        );
    }

    public function test_refill_created_notifies_administrators_and_status_changes_notify_requester(): void
    {
        Notification::fake();

        $admin = User::factory()->manager()->create();
        $barista = User::factory()->barista()->create();
        $otherBarista = User::factory()->barista()->create();
        $ingredient = $this->makeTrackedIngredient(current: '15.000', reorder: '30.000', minimum: '10.000');

        $request = $this->createRefillRequest($barista, $ingredient, '40.000');

        Notification::assertSentTo(
            $admin,
            StaffOperationalNotification::class,
            function (StaffOperationalNotification $notification) use ($admin, $request): bool {
                $data = $notification->toDatabase($admin);

                return $notification->type === StaffNotificationType::RefillRequestCreated
                    && $data['url'] === route('administrator.inventory.refill-requests.show', $request)
                    && $data['severity'] === 'warning';
            },
        );
        Notification::assertNotSentTo($barista, StaffOperationalNotification::class);
        $this->assertTrue(
            StaffNotificationLog::query()
                ->where('type', StaffNotificationType::RefillRequestCreated)
                ->where('channel', StaffNotificationChannel::Email)
                ->where('status', 'sent')
                ->exists(),
        );

        Notification::fake();
        app(InventoryRefillRequestServiceInterface::class)->approve($request, $admin, 'OK');

        Notification::assertSentTo(
            $admin,
            StaffOperationalNotification::class,
            fn (StaffOperationalNotification $notification): bool => $notification->type === StaffNotificationType::RefillRequestApproved,
        );
        Notification::assertSentTo(
            $barista,
            StaffOperationalNotification::class,
            function (StaffOperationalNotification $notification) use ($barista, $request): bool {
                $data = $notification->toDatabase($barista);

                return $notification->type === StaffNotificationType::RefillRequestApproved
                    && $data['url'] === route('barista.refill-requests.show', $request)
                    && ! str_contains(strtolower(json_encode($data)), 'purchase_cost')
                    && ! str_contains(strtolower(json_encode($data)), 'cost_per_unit');
            },
        );
        Notification::assertNotSentTo($otherBarista, StaffOperationalNotification::class);
    }

    public function test_duplicate_stock_event_listener_retry_does_not_resend(): void
    {
        Notification::fake();

        $admin = User::factory()->owner()->create();
        $ingredient = $this->makeTrackedIngredient(current: '50.000', reorder: '30.000', minimum: '10.000');
        $this->recordMovement($ingredient, InventoryTransactionType::ManualReduction, '25.000', $admin);

        $log = StaffNotificationLog::query()
            ->where('type', StaffNotificationType::IngredientLowStock)
            ->where('user_id', $admin->id)
            ->first();
        $this->assertNotNull($log);

        app(StaffNotificationDispatcherInterface::class)->notify(
            StaffNotificationType::IngredientLowStock,
            $log->unique_key,
            StaffNotificationAudience::Administrators,
            StaffNotificationContext::forIngredient($ingredient),
            sendEmail: false,
        );

        $this->assertSame(
            1,
            StaffNotificationLog::query()
                ->where('unique_key', $log->unique_key)
                ->where('user_id', $admin->id)
                ->where('channel', StaffNotificationChannel::Database)
                ->count(),
        );
    }

    public function test_notification_failure_does_not_rollback_inventory_movement(): void
    {
        $admin = User::factory()->owner()->create();
        $ingredient = $this->makeTrackedIngredient(current: '50.000', reorder: '30.000', minimum: '10.000');

        $this->app->bind(StaffNotificationDispatcherInterface::class, InventoryAlertDispatcherThrowingStub::class);

        $this->recordMovement($ingredient, InventoryTransactionType::ManualReduction, '25.000', $admin);

        $this->assertSame('25.000', $ingredient->fresh()->current_stock);
        $this->assertDatabaseCount('inventory_transactions', 1);
        $this->assertSame(0, $admin->notifications()->count());
    }

    public function test_administrator_bell_shows_inventory_alert_deep_link(): void
    {
        $admin = User::factory()->owner()->create();
        $ingredient = $this->makeTrackedIngredient(current: '50.000', reorder: '30.000', minimum: '10.000', name: 'Whole Milk');

        $this->recordMovement($ingredient, InventoryTransactionType::ManualReduction, '25.000', $admin);

        $this->actingAs($admin, 'admin')
            ->get(route('administrator.dashboard'))
            ->assertOk()
            ->assertSee('Whole Milk is running low', false)
            ->assertSee(route('administrator.inventory.index', ['stock_status' => 'low_stock']), false);
    }

    protected function makeTrackedIngredient(
        string $current,
        string $reorder,
        string $minimum,
        string $name = 'Milk',
    ): Ingredient {
        return Ingredient::factory()->create([
            'name' => $name,
            'measurement_unit' => IngredientUnit::Milliliter,
            'base_measurement_unit' => IngredientUnit::Milliliter,
            'current_stock' => $current,
            'reorder_level' => $reorder,
            'minimum_stock' => $minimum,
            'purchase_cost' => '999.00',
            'cost_per_unit' => '9.9900',
            'is_active' => true,
        ]);
    }

    protected function recordMovement(
        Ingredient $ingredient,
        InventoryTransactionType $type,
        string $quantity,
        ?User $actor = null,
        ?int $refillRequestId = null,
    ): void {
        $actor ??= User::factory()->owner()->create();

        $transfer = app(InventoryTransactionTransferInterface::class);
        $transfer->setIngredientId($ingredient->id);
        $transfer->setTransactionType($type->value);
        $transfer->setQuantity($quantity);
        $transfer->setMeasurementUnit(IngredientUnit::Milliliter->value);
        $transfer->setCreatedBy($actor->id);

        if ($refillRequestId !== null) {
            $transfer->setReferenceType('inventory_refill_request');
            $transfer->setReferenceId($refillRequestId);
        }

        app(InventoryServiceInterface::class)->recordTransaction($transfer);
    }

    protected function createRefillRequest(User $barista, Ingredient $ingredient, string $quantity): InventoryRefillRequest
    {
        $transfer = app(InventoryRefillRequestTransferInterface::class);
        $transfer->setIngredientId($ingredient->id);
        $transfer->setQuantity($quantity);
        $transfer->setMeasurementUnit(IngredientUnit::Milliliter->value);
        $transfer->setNotes('Need more soon');

        return app(InventoryRefillRequestServiceInterface::class)->store($barista, $transfer);
    }
}

class InventoryAlertDispatcherThrowingStub implements StaffNotificationDispatcherInterface
{
    public function notify(
        StaffNotificationType $type,
        string $uniqueKey,
        StaffNotificationAudience $audience,
        StaffNotificationContext $context,
        bool $sendEmail = false,
    ): void {
        // Simulated handled failure — delivery skipped without aborting inventory writes.
    }

    public function notifyUser(
        User $user,
        StaffNotificationType $type,
        string $uniqueKey,
        StaffNotificationContext $context,
        bool $sendEmail = false,
    ): void {
        //
    }

    public function recipientsFor(StaffNotificationAudience $audience): Collection
    {
        return collect();
    }
}
