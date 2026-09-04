<?php

namespace Tests\Feature;

use App\Enums\DiningSessionStatus;
use App\Enums\OperationalNotificationType;
use App\Enums\OrderFulfilmentMethod;
use App\Enums\OrderPreparationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PreparationStation;
use App\Enums\ProductServingUnit;
use App\Enums\UserRole;
use App\Events\Order\OrderPlaced;
use App\Events\Order\OrderPreparationStatusChanged;
use App\Events\Realtime\OperationalNotificationBroadcasted;
use App\Models\CafeTable;
use App\Models\DiningSession;
use App\Models\OperationalNotification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Order\OrderServiceInterface;
use App\Services\OrderPreparation\OrderPreparationServiceInterface;
use App\Transfers\Order\OrderStatusTransitionTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OperationalBusinessNotificationWiringTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_retail_order_creates_requires_attention_for_operator_and_admin_without_duplicate(): void
    {
        $owner = User::factory()->owner()->create();
        $operator = User::factory()->operator()->create();
        $barista = User::factory()->barista()->create();
        $customer = User::factory()->customer()->create();

        $order = $this->makeRetailOrder($customer, cash: true);

        event(new OrderPlaced($order));
        event(new OrderPlaced($order));

        $notifications = OperationalNotification::query()
            ->where('type', OperationalNotificationType::OrderRequiresAttention->value)
            ->where('subject_id', $order->id)
            ->get();

        $this->assertCount(1, $notifications);
        $this->assertTrue($notifications->first()->action_required);

        $userIds = $notifications->first()->recipients->pluck('user_id')->all();
        $this->assertContains($owner->id, $userIds);
        $this->assertContains($operator->id, $userIds);
        $this->assertNotContains($barista->id, $userIds);
        $this->assertArrayNotHasKey('total_amount', $notifications->first()->metadata ?? []);
    }

    public function test_upi_retail_order_place_does_not_create_actionable_attention(): void
    {
        $customer = User::factory()->customer()->create();
        User::factory()->owner()->create();
        $order = $this->makeRetailOrder($customer, cash: false);

        event(new OrderPlaced($order));

        $this->assertSame(
            0,
            OperationalNotification::query()
                ->where('type', OperationalNotificationType::OrderRequiresAttention->value)
                ->count(),
        );
    }

    public function test_payment_proof_notifies_operator_admin_and_resolves_on_confirm(): void
    {
        Storage::fake('local');
        $admin = User::factory()->owner()->create();
        $operator = User::factory()->operator()->create();
        $barista = User::factory()->barista()->create();
        $customer = User::factory()->customer()->create();
        $order = $this->makeRetailOrder($customer, cash: false);

        $orders = app(OrderServiceInterface::class);
        $order = $orders->uploadPaymentProof(
            $order,
            $customer,
            'UTRTEST005XYZ',
        );

        $review = OperationalNotification::query()
            ->where('type', OperationalNotificationType::OrderPaymentProofReview->value)
            ->where('subject_id', $order->id)
            ->first();

        $this->assertNotNull($review);
        $this->assertTrue($review->action_required);
        $recipientIds = $review->recipients->pluck('user_id')->all();
        $this->assertContains($operator->id, $recipientIds);
        $this->assertContains($admin->id, $recipientIds);
        $this->assertNotContains($barista->id, $recipientIds);

        $orders->transition($order, $admin, $this->statusTransfer(OrderStatus::PaymentConfirmed));

        $this->assertNotNull($review->fresh()->resolved_at);
        $this->assertSame('payment_confirmed', $review->fresh()->resolution_action);

        $acceptance = OperationalNotification::query()
            ->where('type', OperationalNotificationType::OrderRequiresAcceptance->value)
            ->where('subject_id', $order->id)
            ->first();

        $this->assertNotNull($acceptance);
        $this->assertTrue($acceptance->action_required);

        $orders->transition($order->fresh(), $operator, $this->statusTransfer(OrderStatus::Accepted));
        $this->assertNotNull($acceptance->fresh()->resolved_at);
    }

    public function test_payment_proof_reject_resolves_review_notification(): void
    {
        Storage::fake('local');
        $admin = User::factory()->owner()->create();
        $customer = User::factory()->customer()->create();
        $order = $this->makeRetailOrder($customer, cash: false);
        $orders = app(OrderServiceInterface::class);

        $order = $orders->uploadPaymentProof(
            $order,
            $customer,
            'UTRTEST006XYZ',
        );

        $review = OperationalNotification::query()
            ->where('type', OperationalNotificationType::OrderPaymentProofReview->value)
            ->firstOrFail();

        $orders->rejectPaymentProof($order, $admin, 'Blurry');

        $this->assertNotNull($review->fresh()->resolved_at);
        $this->assertSame('proof_rejected', $review->fresh()->resolution_action);
    }

    public function test_preparation_tickets_notify_correct_stations_and_mixed_creates_both(): void
    {
        User::factory()->barista()->create();
        User::factory()->chef()->create();
        $operator = User::factory()->operator()->create();

        $order = $this->makePaymentConfirmedOrder([
            ['station' => PreparationStation::Bar, 'name' => 'Latte'],
            ['station' => PreparationStation::Kitchen, 'name' => 'Pasta'],
        ]);

        app(OrderServiceInterface::class)->transition(
            $order,
            $operator,
            $this->statusTransfer(OrderStatus::Accepted),
        );

        $pending = OperationalNotification::query()
            ->where('type', OperationalNotificationType::PreparationTicketPending->value)
            ->get();

        $this->assertCount(2, $pending);

        $bar = $pending->first(fn ($n) => str_contains($n->title, 'Bar'));
        $kitchen = $pending->first(fn ($n) => str_contains($n->title, 'Kitchen'));
        $this->assertNotNull($bar);
        $this->assertNotNull($kitchen);

        $this->assertTrue(
            $bar->recipients->every(fn ($r) => $r->role === UserRole::Barista),
        );
        $this->assertTrue(
            $kitchen->recipients->every(fn ($r) => $r->role === UserRole::Chef),
        );

        // Repeated sync must not duplicate.
        app(OrderPreparationServiceInterface::class)->createTicketsForOrder($order->fresh(['items', 'preparations']));
        $this->assertSame(2, OperationalNotification::query()
            ->where('type', OperationalNotificationType::PreparationTicketPending->value)
            ->count());

        $barTicket = $order->fresh('preparations')->preparations->firstWhere('station', PreparationStation::Bar);
        $barista = User::factory()->barista()->create();
        app(OrderPreparationServiceInterface::class)->transition(
            $barTicket,
            $barista,
            OrderPreparationStatus::Accepted,
        );

        $this->assertNotNull($bar->fresh()->resolved_at);
        $this->assertSame(OrderPreparationStatus::Accepted->value, $bar->fresh()->resolution_action);
    }

    public function test_order_cancellation_resolves_station_alerts_and_notifies_informationally(): void
    {
        User::factory()->barista()->create();
        $operator = User::factory()->operator()->create();
        $admin = User::factory()->owner()->create();

        $order = $this->makePaymentConfirmedOrder([
            ['station' => PreparationStation::Bar, 'name' => 'Latte'],
        ]);

        $orders = app(OrderServiceInterface::class);
        $order = $orders->transition($order, $operator, $this->statusTransfer(OrderStatus::Accepted));

        $pending = OperationalNotification::query()
            ->where('type', OperationalNotificationType::PreparationTicketPending->value)
            ->firstOrFail();

        $orders->transition($order->fresh(), $admin, $this->statusTransfer(OrderStatus::Cancelled));

        $this->assertNotNull($pending->fresh()->resolved_at);

        $this->assertTrue(
            OperationalNotification::query()
                ->where('type', OperationalNotificationType::OrderCancelled->value)
                ->where('subject_id', $order->id)
                ->where('action_required', false)
                ->exists(),
        );

        $this->assertTrue(
            OperationalNotification::query()
                ->where('type', OperationalNotificationType::PreparationTicketCancelled->value)
                ->exists(),
        );
    }

    public function test_dining_ready_to_serve_emits_once_for_waiter_when_all_stations_ready(): void
    {
        $waiter = User::factory()->waiter()->create();
        $operator = User::factory()->operator()->create();
        $barista = User::factory()->barista()->create();
        $chef = User::factory()->chef()->create();
        $customer = User::factory()->customer()->create();

        $table = CafeTable::factory()->create(['is_active' => true]);
        $session = DiningSession::query()->create([
            'session_number' => 'DS-OPS-1',
            'cafe_table_id' => $table->id,
            'customer_id' => $customer->id,
            'opened_by_user_id' => $waiter->id,
            'status' => DiningSessionStatus::Open->value,
            'guest_count' => 2,
            'table_name_snapshot' => $table->snapshotLabel(),
            'customer_name_snapshot' => $customer->name,
            'customer_phone_snapshot' => $customer->phone,
            'opened_at' => now(),
            'payment_status' => PaymentStatus::Pending->value,
            'subtotal_amount' => '0.00',
            'discount_amount' => '0.00',
            'tax_amount' => '0.00',
            'taxable_amount' => '0.00',
            'total_amount' => '0.00',
        ]);

        $bar = $this->makeVariant(PreparationStation::Bar, 'Latte');
        $kitchen = $this->makeVariant(PreparationStation::Kitchen, 'Pasta');

        $order = app(OrderServiceInterface::class)->placeDiningRound($waiter, $session, [
            ['product_variant_id' => $bar->id, 'quantity' => 1],
            ['product_variant_id' => $kitchen->id, 'quantity' => 1],
        ]);

        $this->assertSame(2, OperationalNotification::query()
            ->where('type', OperationalNotificationType::PreparationTicketPending->value)
            ->count());

        $this->assertSame(0, OperationalNotification::query()
            ->where('type', OperationalNotificationType::DiningReadyToServe->value)
            ->count());

        $prep = app(OrderPreparationServiceInterface::class);
        $barTicket = $order->preparations->firstWhere('station', PreparationStation::Bar);
        $kitchenTicket = $order->preparations->firstWhere('station', PreparationStation::Kitchen);

        $prep->transition($barTicket, $barista, OrderPreparationStatus::Accepted);
        $prep->transition($barTicket->fresh(), $barista, OrderPreparationStatus::Preparing);
        $prep->transition($barTicket->fresh(), $barista, OrderPreparationStatus::Ready);

        $this->assertSame(0, OperationalNotification::query()
            ->where('type', OperationalNotificationType::DiningReadyToServe->value)
            ->count());

        $prep->transition($kitchenTicket, $chef, OrderPreparationStatus::Accepted);
        $prep->transition($kitchenTicket->fresh(), $chef, OrderPreparationStatus::Preparing);
        $prep->transition($kitchenTicket->fresh(), $chef, OrderPreparationStatus::Ready);

        $ready = OperationalNotification::query()
            ->where('type', OperationalNotificationType::DiningReadyToServe->value)
            ->where('subject_id', $order->id)
            ->get();

        $this->assertCount(1, $ready);
        $this->assertTrue($ready->first()->action_required);

        $ids = $ready->first()->recipients->pluck('user_id')->all();
        $this->assertContains($waiter->id, $ids);
        $this->assertContains($operator->id, $ids);
        $this->assertNotContains($barista->id, $ids);

        // Replay readiness path must not duplicate.
        event(new OrderPreparationStatusChanged(
            $kitchenTicket->fresh(),
            OrderPreparationStatus::Preparing,
            OrderPreparationStatus::Ready,
        ));

        $this->assertSame(1, OperationalNotification::query()
            ->where('type', OperationalNotificationType::DiningReadyToServe->value)
            ->count());
    }

    public function test_broadcast_failure_does_not_break_business_flow(): void
    {
        Event::listen(OperationalNotificationBroadcasted::class, function (): void {
            throw new \RuntimeException('ws down');
        });

        $operator = User::factory()->operator()->create();
        User::factory()->owner()->create();
        $customer = User::factory()->customer()->create();
        $order = $this->makeRetailOrder($customer, cash: true);

        event(new OrderPlaced($order));

        $this->assertDatabaseHas('operational_notifications', [
            'type' => OperationalNotificationType::OrderRequiresAttention->value,
            'subject_id' => $order->id,
        ]);

        $updated = app(OrderServiceInterface::class)->transition(
            $order,
            $operator,
            $this->statusTransfer(OrderStatus::Accepted),
        );

        $this->assertSame(OrderStatus::Accepted, $updated->status);
    }

    public function test_payload_excludes_financial_and_recipe_fields(): void
    {
        Event::fake([OperationalNotificationBroadcasted::class]);

        User::factory()->owner()->create();
        $customer = User::factory()->customer()->create();
        $order = $this->makeRetailOrder($customer, cash: true);

        event(new OrderPlaced($order));

        Event::assertDispatched(OperationalNotificationBroadcasted::class, function (OperationalNotificationBroadcasted $event): bool {
            $payload = $event->broadcastWith();

            $this->assertArrayHasKey('type', $payload);
            $this->assertArrayHasKey('recipient_id', $payload);
            $this->assertArrayNotHasKey('total_amount', $payload);
            $this->assertArrayNotHasKey('customer_email', $payload);
            $this->assertArrayNotHasKey('recipe', $payload);
            $this->assertArrayNotHasKey('metadata', $payload);

            return true;
        });
    }

    protected function makeRetailOrder(User $customer, bool $cash): Order
    {
        $variant = $this->makeVariant(PreparationStation::Bar, 'Latte');

        $order = Order::factory()
            ->takeaway()
            ->when($cash, fn ($factory) => $factory->cash())
            ->create([
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'customer_email' => $customer->email,
                'customer_phone' => $customer->phone,
                'status' => OrderStatus::PendingPayment,
                'payment_status' => PaymentStatus::Pending,
                'payment_method' => $cash ? PaymentMethod::Cash : PaymentMethod::Manual,
                'fulfilment_method' => OrderFulfilmentMethod::Takeaway,
            ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'recipe_id' => $variant->recipe?->id,
            'preparation_station' => PreparationStation::Bar,
            'product_name' => 'Latte',
            'variant_name' => 'Regular',
            'unit_price' => '10.00',
            'quantity' => 1,
            'line_subtotal' => '10.00',
        ]);

        return $order->fresh(['items', 'customer']);
    }

    /**
     * @param  list<array{station: PreparationStation, name: string}>  $items
     */
    protected function makePaymentConfirmedOrder(array $items): Order
    {
        $customer = User::factory()->customer()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::PaymentConfirmed,
            'payment_status' => PaymentStatus::Confirmed,
            'payment_method' => PaymentMethod::Manual,
            'fulfilment_method' => OrderFulfilmentMethod::Takeaway,
            'payment_confirmed_at' => now(),
        ]);

        foreach ($items as $item) {
            $variant = $this->makeVariant($item['station'], $item['name']);

            OrderItem::factory()->create([
                'order_id' => $order->id,
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'recipe_id' => $variant->recipe?->id,
                'preparation_station' => $item['station'],
                'product_name' => $item['name'],
                'variant_name' => 'Regular',
                'unit_price' => '10.00',
                'quantity' => 1,
                'line_subtotal' => '10.00',
            ]);
        }

        return $order->fresh('items');
    }

    protected function makeVariant(PreparationStation $station, string $name): ProductVariant
    {
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'name' => $name,
            'is_active' => true,
            'is_available' => true,
            'preparation_station' => $station,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Regular',
            'serving_size_value' => '300.000',
            'serving_size_unit' => ProductServingUnit::Milliliter,
            'price' => '10.00',
            'is_active' => true,
            'is_available' => true,
        ]);

        Recipe::factory()->withDefaultLine()->create([
            'product_variant_id' => $variant->id,
        ]);

        return $variant->fresh('recipe');
    }

    protected function statusTransfer(OrderStatus $status): OrderStatusTransitionTransfer
    {
        $transfer = new OrderStatusTransitionTransfer;
        $transfer->setStatus($status->value);
        $transfer->setNotes(null);

        return $transfer;
    }
}
