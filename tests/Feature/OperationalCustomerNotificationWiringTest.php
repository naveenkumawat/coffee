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
use App\Events\Dining\DiningBillReady;
use App\Events\Dining\DiningPaymentConfirmed;
use App\Events\Dining\DiningRoundPlaced;
use App\Events\Order\OrderPlaced;
use App\Events\Order\OrderPreparationStatusChanged;
use App\Events\Order\OrderStatusChanged;
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
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OperationalCustomerNotificationWiringTest extends TestCase
{
    use RefreshDatabase;

    public function test_own_order_creates_customer_placed_notification_not_for_other_customer(): void
    {
        User::factory()->owner()->create();
        $customer = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();
        $order = $this->makeRetailOrder($customer, cash: true);

        event(new OrderPlaced($order));

        $notification = OperationalNotification::query()
            ->where('type', OperationalNotificationType::CustomerOrderPlaced->value)
            ->where('subject_id', $order->id)
            ->first();

        $this->assertNotNull($notification);
        $this->assertFalse($notification->action_required);
        $this->assertSame('/orders/'.$order->id, $notification->action_url);

        $recipientIds = $notification->recipients->pluck('user_id')->all();
        $this->assertContains($customer->id, $recipientIds);
        $this->assertNotContains($other->id, $recipientIds);
        $this->assertArrayNotHasKey('total_amount', $notification->metadata ?? []);
        $this->assertArrayNotHasKey('recipe', $notification->metadata ?? []);
    }

    public function test_walk_in_dining_with_null_customer_creates_no_customer_notification(): void
    {
        $waiter = User::factory()->waiter()->create();
        $table = CafeTable::factory()->create(['is_active' => true]);
        $session = DiningSession::query()->create([
            'session_number' => 'DS-WALK-1',
            'cafe_table_id' => $table->id,
            'customer_id' => null,
            'opened_by_user_id' => $waiter->id,
            'status' => DiningSessionStatus::Open->value,
            'guest_count' => 2,
            'table_name_snapshot' => $table->snapshotLabel(),
            'opened_at' => now(),
            'payment_status' => PaymentStatus::Pending->value,
            'subtotal_amount' => '0.00',
            'discount_amount' => '0.00',
            'tax_amount' => '0.00',
            'taxable_amount' => '0.00',
            'total_amount' => '0.00',
        ]);
        $order = Order::factory()->create([
            'customer_id' => null,
            'dining_session_id' => $session->id,
            'fulfilment_method' => OrderFulfilmentMethod::DineIn,
            'status' => OrderStatus::Accepted,
        ]);

        event(new DiningRoundPlaced($order, $session));

        $this->assertSame(
            0,
            OperationalNotification::query()
                ->where('type', 'like', 'customer.%')
                ->count(),
        );
    }

    public function test_ready_notification_once_with_takeaway_wording(): void
    {
        $admin = User::factory()->owner()->create();
        $customer = User::factory()->customer()->create();
        $order = $this->makeRetailOrder($customer, cash: true);

        $orders = app(OrderServiceInterface::class);
        $orders->transition($order, $admin, $this->statusTransfer(OrderStatus::Accepted));
        $orders->transition($order->fresh(), $admin, $this->statusTransfer(OrderStatus::Preparing));
        $orders->transition($order->fresh(), $admin, $this->statusTransfer(OrderStatus::ReadyForPickup));

        event(new OrderStatusChanged(
            $order->fresh(),
            OrderStatus::Preparing,
            OrderStatus::ReadyForPickup,
        ));

        $notifications = OperationalNotification::query()
            ->where('type', OperationalNotificationType::CustomerOrderReady->value)
            ->where('subject_id', $order->id)
            ->get();

        $this->assertCount(1, $notifications);
        $this->assertStringContainsString('Ready for Pickup', $notifications->first()->message);
        $this->assertTrue($notifications->first()->recipients->contains('user_id', $customer->id));
    }

    public function test_delivery_ready_uses_delivery_wording(): void
    {
        $admin = User::factory()->owner()->create();
        $customer = User::factory()->customer()->create();
        $order = $this->makeRetailOrder($customer, cash: true);
        $order->update(['fulfilment_method' => OrderFulfilmentMethod::Delivery]);

        $orders = app(OrderServiceInterface::class);
        $orders->transition($order->fresh(), $admin, $this->statusTransfer(OrderStatus::Accepted));
        $orders->transition($order->fresh(), $admin, $this->statusTransfer(OrderStatus::Preparing));
        $orders->transition($order->fresh(), $admin, $this->statusTransfer(OrderStatus::ReadyForPickup));

        $notification = OperationalNotification::query()
            ->where('type', OperationalNotificationType::CustomerOrderReady->value)
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringContainsString('Ready for Delivery', $notification->message);
    }

    public function test_payment_confirm_and_reject_notify_customer_only(): void
    {
        Storage::fake('local');
        $admin = User::factory()->owner()->create();
        $customer = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();
        $order = $this->makeRetailOrder($customer, cash: false);

        $orders = app(OrderServiceInterface::class);
        $order = $orders->uploadPaymentProof(
            $order,
            $customer,
            'UTRTEST003XYZ',
        );

        $proof = OperationalNotification::query()
            ->where('type', OperationalNotificationType::CustomerPaymentProofReceived->value)
            ->first();
        $this->assertNotNull($proof);
        $this->assertContains($customer->id, $proof->recipients->pluck('user_id')->all());
        $this->assertNotContains($other->id, $proof->recipients->pluck('user_id')->all());

        $orders->rejectPaymentProof($order->fresh(), $admin, 'Blurry screenshot');

        $rejected = OperationalNotification::query()
            ->where('type', OperationalNotificationType::CustomerPaymentRejected->value)
            ->first();
        $this->assertNotNull($rejected);
        $this->assertStringContainsString('Blurry screenshot', $rejected->message);
        $this->assertContains($customer->id, $rejected->recipients->pluck('user_id')->all());

        $order = $orders->uploadPaymentProof(
            $order->fresh(),
            $customer,
            'UTRTEST004XYZ',
        );
        $orders->transition($order->fresh(), $admin, $this->statusTransfer(OrderStatus::PaymentConfirmed));

        $confirmed = OperationalNotification::query()
            ->where('type', OperationalNotificationType::CustomerPaymentConfirmed->value)
            ->where('subject_id', $order->id)
            ->first();
        $this->assertNotNull($confirmed);
        $this->assertContains($customer->id, $confirmed->recipients->pluck('user_id')->all());
    }

    public function test_cancel_and_reject_create_customer_notifications(): void
    {
        $admin = User::factory()->owner()->create();
        $customer = User::factory()->customer()->create();
        $order = $this->makeRetailOrder($customer, cash: true);

        app(OrderServiceInterface::class)->transition(
            $order,
            $admin,
            $this->statusTransfer(OrderStatus::Cancelled),
        );

        $this->assertSame(
            1,
            OperationalNotification::query()
                ->where('type', OperationalNotificationType::CustomerOrderCancelled->value)
                ->where('subject_id', $order->id)
                ->count(),
        );

        $order2 = $this->makeRetailOrder($customer, cash: true);
        app(OrderServiceInterface::class)->transition(
            $order2,
            $admin,
            $this->statusTransfer(OrderStatus::Rejected),
        );

        $this->assertSame(
            1,
            OperationalNotification::query()
                ->where('type', OperationalNotificationType::CustomerOrderRejected->value)
                ->where('subject_id', $order2->id)
                ->count(),
        );
    }

    public function test_customer_placed_is_deduped(): void
    {
        User::factory()->owner()->create();
        $customer = User::factory()->customer()->create();
        $order = $this->makeRetailOrder($customer, cash: false);

        event(new OrderPlaced($order));
        event(new OrderPlaced($order));

        $this->assertSame(
            1,
            OperationalNotification::query()
                ->where('type', OperationalNotificationType::CustomerOrderPlaced->value)
                ->count(),
        );
    }

    public function test_broadcast_payload_is_customer_safe(): void
    {
        Event::fake([OperationalNotificationBroadcasted::class]);
        User::factory()->owner()->create();
        $customer = User::factory()->customer()->create();
        $order = $this->makeRetailOrder($customer, cash: true);

        event(new OrderPlaced($order));

        Event::assertDispatched(OperationalNotificationBroadcasted::class, function (OperationalNotificationBroadcasted $event) use ($customer): bool {
            if ($event->userId !== $customer->id) {
                return false;
            }

            $payload = $event->payload;
            $forbidden = ['total_amount', 'email', 'recipe', 'margin', 'ingredient', 'staff', 'password'];
            foreach ($forbidden as $key) {
                if (array_key_exists($key, $payload)) {
                    return false;
                }
            }

            return ($payload['type'] ?? null) === OperationalNotificationType::CustomerOrderPlaced->value
                && isset($payload['recipient_id'], $payload['title'], $payload['message'], $payload['action_url']);
        });
    }

    public function test_customer_cannot_access_another_recipients_ack(): void
    {
        User::factory()->owner()->create();
        $customer = User::factory()->customer()->create();
        $intruder = User::factory()->customer()->create();
        $order = $this->makeRetailOrder($customer, cash: true);
        event(new OrderPlaced($order));

        $recipient = OperationalNotification::query()
            ->where('type', OperationalNotificationType::CustomerOrderPlaced->value)
            ->first()
            ?->recipients
            ->first();

        $this->assertNotNull($recipient);

        Sanctum::actingAs($intruder);
        $this->postJson('/api/v1/notifications/'.$recipient->id.'/delivered')
            ->assertNotFound();
    }

    public function test_staff_notifications_still_created_alongside_customer(): void
    {
        $owner = User::factory()->owner()->create();
        $customer = User::factory()->customer()->create();
        $order = $this->makeRetailOrder($customer, cash: true);

        event(new OrderPlaced($order));

        $this->assertSame(
            1,
            OperationalNotification::query()
                ->where('type', OperationalNotificationType::OrderRequiresAttention->value)
                ->count(),
        );
        $this->assertSame(
            1,
            OperationalNotification::query()
                ->where('type', OperationalNotificationType::CustomerOrderPlaced->value)
                ->count(),
        );

        $staff = OperationalNotification::query()
            ->where('type', OperationalNotificationType::OrderRequiresAttention->value)
            ->first();
        $this->assertContains($owner->id, $staff->recipients->pluck('user_id')->all());
        $this->assertNotContains($customer->id, $staff->recipients->pluck('user_id')->all());
    }

    public function test_dining_authenticated_customer_gets_round_and_ready_once(): void
    {
        User::factory()->owner()->create();
        $waiter = User::factory()->waiter()->create();
        User::factory()->operator()->create();
        $barista = User::factory()->barista()->create();
        $customer = User::factory()->customer()->create();
        $table = CafeTable::factory()->create(['is_active' => true]);
        $session = DiningSession::query()->create([
            'session_number' => 'DS-CUST-1',
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

        $bar = $this->makeVariant(PreparationStation::Bar, 'Dining Latte');
        $order = app(OrderServiceInterface::class)->placeDiningRound($waiter, $session, [
            ['product_variant_id' => $bar->id, 'quantity' => 1],
        ]);

        $this->assertSame(
            1,
            OperationalNotification::query()
                ->where('type', OperationalNotificationType::CustomerDiningRoundUpdated->value)
                ->where('subject_id', $order->id)
                ->count(),
        );

        $prep = app(OrderPreparationServiceInterface::class);
        $ticket = $order->preparations->firstWhere('station', PreparationStation::Bar);
        $this->assertNotNull($ticket);

        $prep->transition($ticket, $barista, OrderPreparationStatus::Accepted);
        $prep->transition($ticket->fresh(), $barista, OrderPreparationStatus::Preparing);
        $prep->transition($ticket->fresh(), $barista, OrderPreparationStatus::Ready);
        event(new OrderPreparationStatusChanged(
            $ticket->fresh(['order.preparations', 'order.diningSession', 'order.customer']),
            OrderPreparationStatus::Preparing,
            OrderPreparationStatus::Ready,
        ));

        $this->assertSame(
            1,
            OperationalNotification::query()
                ->where('type', OperationalNotificationType::CustomerDiningReady->value)
                ->count(),
        );
    }

    public function test_dining_bill_and_session_closed_notify_customer(): void
    {
        $operator = User::factory()->operator()->create();
        $customer = User::factory()->customer()->create();
        $waiter = User::factory()->waiter()->create();
        $table = CafeTable::factory()->create(['is_active' => true]);
        $session = DiningSession::query()->create([
            'session_number' => 'DS-100',
            'cafe_table_id' => $table->id,
            'customer_id' => $customer->id,
            'opened_by_user_id' => $waiter->id,
            'status' => DiningSessionStatus::AwaitingPayment->value,
            'guest_count' => 2,
            'table_name_snapshot' => $table->snapshotLabel(),
            'customer_name_snapshot' => $customer->name,
            'opened_at' => now(),
            'payment_status' => PaymentStatus::Pending->value,
            'subtotal_amount' => '10.00',
            'discount_amount' => '0.00',
            'tax_amount' => '0.00',
            'taxable_amount' => '10.00',
            'total_amount' => '10.00',
        ]);

        event(new DiningBillReady($session, $operator));
        event(new DiningPaymentConfirmed($session, $operator));

        $this->assertSame(
            1,
            OperationalNotification::query()
                ->where('type', OperationalNotificationType::CustomerDiningBillRequested->value)
                ->count(),
        );
        $this->assertSame(
            1,
            OperationalNotification::query()
                ->where('type', OperationalNotificationType::CustomerDiningSessionClosed->value)
                ->count(),
        );
        $this->assertSame(
            1,
            OperationalNotification::query()
                ->where('type', OperationalNotificationType::CustomerPaymentConfirmed->value)
                ->where('subject_type', $session->getMorphClass())
                ->count(),
        );
    }

    protected function makeRetailOrder(User $customer, bool $cash): Order
    {
        $variant = $this->makeVariant(PreparationStation::Bar, 'Latte');

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
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
