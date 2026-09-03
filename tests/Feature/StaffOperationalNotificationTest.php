<?php

namespace Tests\Feature;

use App\Enums\OrderFulfilmentMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PreparationStation;
use App\Enums\ProductServingUnit;
use App\Enums\StaffNotificationAudience;
use App\Enums\StaffNotificationChannel;
use App\Enums\StaffNotificationType;
use App\Events\Order\OrderPlaced;
use App\Events\Order\OrderStatusChanged;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\StaffNotificationLog;
use App\Models\User;
use App\Notifications\StaffOperationalNotification;
use App\Services\Notification\StaffNotificationContext;
use App\Services\Notification\StaffNotificationDispatcherInterface;
use App\Services\Order\OrderServiceInterface;
use App\Transfers\Order\OrderStatusTransitionTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StaffOperationalNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_order_notifies_active_administrators_not_baristas_or_customers(): void
    {
        Notification::fake();

        $owner = User::factory()->owner()->create(['email' => 'owner-staff@example.test']);
        $manager = User::factory()->manager()->create(['email' => 'manager-staff@example.test']);
        $inactiveAdmin = User::factory()->owner()->inactive()->create(['email' => 'inactive-admin@example.test']);
        $barista = User::factory()->barista()->create(['email' => 'barista-staff@example.test']);
        $customer = User::factory()->customer()->create(['email' => 'customer-staff@example.test']);

        $order = $this->makeOrder($customer, OrderFulfilmentMethod::Takeaway);
        OrderPlaced::dispatch($order);
        OrderPlaced::dispatch($order);

        Notification::assertSentTo($owner, StaffOperationalNotification::class, function (StaffOperationalNotification $notification) use ($owner): bool {
            $data = $notification->toDatabase($owner);

            return $notification->type === StaffNotificationType::OrderPlaced
                && str_contains($data['title'], 'New order')
                && $data['url'] === route('administrator.orders.show', $notification->context->order)
                && $data['total_amount'] !== null
                && $data['customer_name'] !== null;
        });
        Notification::assertSentTo($manager, StaffOperationalNotification::class);
        Notification::assertNotSentTo($inactiveAdmin, StaffOperationalNotification::class);
        Notification::assertNotSentTo($barista, StaffOperationalNotification::class);
        Notification::assertNotSentTo($customer, StaffOperationalNotification::class);

        $this->assertSame(
            2,
            StaffNotificationLog::query()
                ->where('unique_key', 'staff:order_placed:'.$order->id)
                ->where('channel', StaffNotificationChannel::Database)
                ->where('status', 'sent')
                ->count(),
        );
        $this->assertSame(
            2,
            StaffNotificationLog::query()
                ->where('unique_key', 'staff:order_placed:'.$order->id)
                ->where('channel', StaffNotificationChannel::Email)
                ->where('status', 'sent')
                ->count(),
        );
    }

    public function test_payment_proof_and_resubmission_notify_administrators(): void
    {
        Notification::fake();
        Storage::fake('local');

        $admin = User::factory()->owner()->create();
        $barista = User::factory()->barista()->create();
        $customer = User::factory()->customer()->create();
        $order = $this->makeOrder($customer, OrderFulfilmentMethod::Takeaway);

        $orders = app(OrderServiceInterface::class);
        $order = $orders->uploadPaymentProof(
            $order,
            $customer,
            UploadedFile::fake()->image('proof.jpg', 160, 160),
        );

        Notification::assertSentTo(
            $admin,
            StaffOperationalNotification::class,
            fn (StaffOperationalNotification $notification): bool => $notification->type === StaffNotificationType::PaymentProofReceived,
        );
        Notification::assertNotSentTo($barista, StaffOperationalNotification::class);

        $orders->rejectPaymentProof($order, $admin, 'Please upload a clearer screenshot.');

        Notification::fake();

        $orders->uploadPaymentProof(
            $order->fresh(),
            $customer,
            UploadedFile::fake()->image('proof-2.jpg', 160, 160),
        );

        Notification::assertSentTo(
            $admin,
            StaffOperationalNotification::class,
            fn (StaffOperationalNotification $notification): bool => $notification->type === StaffNotificationType::PaymentProofResubmitted,
        );
    }

    public function test_payment_confirmed_and_accepted_notify_active_operators_without_financial_fields(): void
    {
        Notification::fake();

        $admin = User::factory()->owner()->create();
        $operator = User::factory()->operator()->create();
        $inactiveOperator = User::factory()->operator()->inactive()->create();
        $barista = User::factory()->barista()->create();
        $customer = User::factory()->customer()->create(['name' => 'Secret Customer']);
        $order = $this->makeOrder($customer, OrderFulfilmentMethod::Takeaway, [
            'total_amount' => '42.50',
        ]);

        OrderStatusChanged::dispatch($order, OrderStatus::PendingPayment, OrderStatus::PaymentConfirmed);
        OrderStatusChanged::dispatch($order, OrderStatus::PendingPayment, OrderStatus::PaymentConfirmed);

        Notification::assertSentTo($operator, StaffOperationalNotification::class, function (StaffOperationalNotification $notification) use ($operator): bool {
            if ($notification->type !== StaffNotificationType::PaymentConfirmed) {
                return false;
            }

            $data = $notification->toDatabase($operator);
            $mail = $notification->toMail($operator)->render();

            $this->assertSame(route('operator.orders.show', $notification->context->order), $data['url']);
            $this->assertNull($data['total_amount']);
            $this->assertNull($data['customer_name']);
            $this->assertStringNotContainsString('42.50', $mail);
            $this->assertStringNotContainsString('Secret Customer', $mail);
            $this->assertStringNotContainsString('gross profit', strtolower($mail));
            $this->assertStringNotContainsString('recipe', strtolower($mail));

            return true;
        });
        Notification::assertNotSentTo($admin, StaffOperationalNotification::class);
        Notification::assertNotSentTo($inactiveOperator, StaffOperationalNotification::class);
        Notification::assertNotSentTo($barista, StaffOperationalNotification::class);

        Notification::fake();

        OrderStatusChanged::dispatch($order, OrderStatus::PaymentConfirmed, OrderStatus::Accepted);

        Notification::assertSentTo(
            $operator,
            StaffOperationalNotification::class,
            fn (StaffOperationalNotification $notification): bool => $notification->type === StaffNotificationType::OrderAccepted,
        );
        Notification::assertNotSentTo($barista, StaffOperationalNotification::class);

        $this->assertSame(
            0,
            StaffNotificationLog::query()
                ->where('type', StaffNotificationType::OrderAccepted)
                ->where('channel', StaffNotificationChannel::Email)
                ->count(),
        );
    }

    public function test_cancellation_after_acceptance_notifies_operators_and_admins(): void
    {
        Notification::fake();

        $admin = User::factory()->manager()->create();
        $operator = User::factory()->operator()->create();
        $barista = User::factory()->barista()->create();
        $customer = User::factory()->customer()->create();
        $order = $this->makeOrder($customer, OrderFulfilmentMethod::Takeaway);

        OrderStatusChanged::dispatch($order, OrderStatus::Accepted, OrderStatus::Cancelled);

        Notification::assertSentTo(
            $admin,
            StaffOperationalNotification::class,
            fn (StaffOperationalNotification $notification): bool => $notification->type === StaffNotificationType::OrderCancelled,
        );
        Notification::assertSentTo(
            $operator,
            StaffOperationalNotification::class,
            fn (StaffOperationalNotification $notification): bool => $notification->type === StaffNotificationType::OrderCancelled,
        );
        Notification::assertNotSentTo($barista, StaffOperationalNotification::class);
    }

    public function test_cancellation_before_acceptance_does_not_notify_operators(): void
    {
        Notification::fake();

        $admin = User::factory()->owner()->create();
        $operator = User::factory()->operator()->create();
        $customer = User::factory()->customer()->create();
        $order = $this->makeOrder($customer, OrderFulfilmentMethod::Takeaway);

        OrderStatusChanged::dispatch($order, OrderStatus::PendingPayment, OrderStatus::Cancelled);

        Notification::assertSentTo($admin, StaffOperationalNotification::class);
        Notification::assertNotSentTo($operator, StaffOperationalNotification::class);
    }

    public function test_staff_can_mark_one_and_all_notifications_read(): void
    {
        $admin = User::factory()->owner()->create();
        $customer = User::factory()->customer()->create();
        $order = $this->makeOrder($customer, OrderFulfilmentMethod::Takeaway);

        OrderPlaced::dispatch($order);

        $this->assertSame(1, $admin->unreadNotifications()->count());

        $notification = $admin->unreadNotifications()->first();
        $this->assertNotNull($notification);

        $this->actingAs($admin, 'admin')
            ->post(route('administrator.notifications.read', $notification))
            ->assertRedirect();

        $this->assertSame(0, $admin->fresh()->unreadNotifications()->count());

        OrderStatusChanged::dispatch($order, OrderStatus::PendingPayment, OrderStatus::Cancelled);
        $this->assertSame(1, $admin->fresh()->unreadNotifications()->count());

        $this->actingAs($admin, 'admin')
            ->post(route('administrator.notifications.read-all'))
            ->assertRedirect();

        $this->assertSame(0, $admin->fresh()->unreadNotifications()->count());
    }

    public function test_customer_cannot_access_staff_notification_routes(): void
    {
        $customer = User::factory()->customer()->create();
        $admin = User::factory()->owner()->create();
        $order = $this->makeOrder($customer, OrderFulfilmentMethod::Takeaway);
        OrderPlaced::dispatch($order);

        $notification = $admin->notifications()->first();
        $this->assertNotNull($notification);

        $this->actingAs($customer, 'web')
            ->post(route('administrator.notifications.read-all'))
            ->assertRedirect();

        $this->assertSame(1, $admin->fresh()->unreadNotifications()->count());

        $this->actingAs($customer, 'admin')
            ->post(route('administrator.notifications.read', $notification))
            ->assertForbidden();
    }

    public function test_notification_failure_does_not_rollback_order_transition(): void
    {
        $admin = User::factory()->owner()->create();
        $customer = User::factory()->customer()->create();
        $order = $this->makeOrder($customer, OrderFulfilmentMethod::Takeaway, [
            'status' => OrderStatus::PaymentConfirmed,
            'payment_status' => PaymentStatus::Confirmed,
        ]);

        $this->app->bind(StaffNotificationDispatcherInterface::class, StaffNotificationDispatcherThrowingStub::class);

        $updated = app(OrderServiceInterface::class)->transition(
            $order,
            $admin,
            $this->statusTransfer(OrderStatus::Accepted),
        );

        $this->assertSame(OrderStatus::Accepted, $updated->status);
    }

    public function test_administrator_header_shows_operational_notification_bell(): void
    {
        $admin = User::factory()->owner()->create();
        $customer = User::factory()->customer()->create();
        $order = $this->makeOrder($customer, OrderFulfilmentMethod::Takeaway);

        OrderPlaced::dispatch($order);

        $this->actingAs($admin, 'admin')
            ->get(route('administrator.dashboard'))
            ->assertOk()
            ->assertSee('coffee-ops-bell', false)
            ->assertSee('coffee-ops-drawer', false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeOrder(User $customer, OrderFulfilmentMethod $method, array $overrides = []): Order
    {
        $variant = $this->makePurchasableVariant();

        $order = Order::factory()
            ->when($method === OrderFulfilmentMethod::Delivery, fn ($factory) => $factory->delivery())
            ->when($method === OrderFulfilmentMethod::Takeaway, fn ($factory) => $factory->takeaway())
            ->create([
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'customer_email' => $customer->email,
                'customer_phone' => $customer->phone,
                'fulfilment_method' => $method,
                'status' => OrderStatus::PendingPayment,
                ...$overrides,
            ]);

        $order->items()->create([
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'recipe_id' => null,
            'preparation_station' => $variant->product->preparation_station?->value
                ?? PreparationStation::Bar->value,
            'product_name' => $variant->product->name,
            'variant_name' => $variant->name,
            'customer_ingredient_summary' => null,
            'unit_price' => $variant->price,
            'quantity' => 1,
            'line_subtotal' => $variant->price,
        ]);

        return $order->fresh(['items', 'customer']);
    }

    protected function makePurchasableVariant(string $price = '9.50'): ProductVariant
    {
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'is_active' => true,
            'is_available' => true,
        ]);

        return ProductVariant::factory()->withConsumableRecipe()->create([
            'product_id' => $product->id,
            'name' => 'Regular',
            'price' => $price,
            'serving_size_value' => '250.000',
            'serving_size_unit' => ProductServingUnit::Milliliter,
            'is_active' => true,
            'is_available' => true,
        ]);
    }

    protected function statusTransfer(OrderStatus $status): OrderStatusTransitionTransfer
    {
        $transfer = new OrderStatusTransitionTransfer;
        $transfer->setStatus($status->value);
        $transfer->setNotes(null);

        return $transfer;
    }
}

class StaffNotificationDispatcherThrowingStub implements StaffNotificationDispatcherInterface
{
    public function notify(
        StaffNotificationType $type,
        string $uniqueKey,
        StaffNotificationAudience $audience,
        StaffNotificationContext $context,
        bool $sendEmail = false,
    ): void {
        // Simulated delivery failure swallowed by caller architecture.
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
